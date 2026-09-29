<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SocialIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Account creation that does not require an account.
 *
 * The app mints an identity on first launch so someone can scan, hit the
 * paywall and subscribe without ever seeing a registration form. Signing in
 * with Apple or Google later upgrades that same row rather than creating a new
 * one, so a purchase made anonymously survives the sign-in.
 *
 * `rc_user_id` is the load-bearing part. It is handed to RevenueCat as the app
 * user id and must never change for a given person, or an entitlement they paid
 * for ends up attached to an identity the app no longer uses.
 */
class DeviceAuthController extends Controller
{
    /**
     * Mint (or recover) an anonymous account for a device.
     *
     * POST /api/auth/anonymous  { install_id }
     */
    public function anonymous(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'install_id' => ['required', 'string', 'min:8', 'max:64'],
        ]);

        // Same install asking twice — a retry, or a reinstall that kept its
        // preferences. Return the same account rather than orphaning whatever
        // it may already have bought.
        $user = User::where('install_id', $validated['install_id'])
            ->where('auth_provider', 'anonymous')
            ->first();

        if (!$user) {
            $user = User::create([
                'install_id' => $validated['install_id'],
                'auth_provider' => 'anonymous',
                'rc_user_id' => $this->mintRcUserId(),
            ]);
        }

        return $this->issue($user, 'anonymous account ready');
    }

    /**
     * Exchange a legacy backend token for a Sanctum token.
     *
     * POST /api/auth/legacy  { legacy_token, install_id }
     *
     * This is what stops the ~1,165 imported accounts being logged out by the
     * migration. An already-installed client still holds the token the old PHP
     * backend gave it; we recognise it once, hand back a real token, and the
     * user keeps their history without ever seeing a login screen.
     */
    public function legacy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'legacy_token' => ['required', 'string', 'max:64'],
            'install_id' => ['nullable', 'string', 'min:8', 'max:64'],
        ]);

        $user = User::where('legacy_token', $validated['legacy_token'])->first();

        if (!$user) {
            // Not an error worth alarming the client about: the overwhelming
            // majority of legacy tokens belong to accounts that were never
            // imported. The app falls back to creating an anonymous one.
            return response()->json([
                'message' => 'Unrecognised token.',
                'recognised' => false,
            ], 404);
        }

        if (!empty($validated['install_id']) && $user->install_id !== $validated['install_id']) {
            $user->install_id = $validated['install_id'];
        }

        if (!$user->rc_user_id) {
            $user->rc_user_id = $this->mintRcUserId();
        }

        $user->last_seen_at = now();
        $user->save();

        return $this->issue($user, 'welcome back');
    }

    /**
     * Record the onboarding answers.
     *
     * POST /api/auth/profile  { role?, use_case? }   (authenticated)
     *
     * Separate from sign-up on purpose: these are personalisation, not
     * registration, and asking for them must never gate the product.
     */
    public function profile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['nullable', 'string', 'max:255'],
            'use_case' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->fill(array_filter($validated, fn ($v) => $v !== null));
        $user->last_seen_at = now();
        $user->save();

        return response()->json(['message' => 'Saved.', 'user' => $user->fresh()]);
    }

    /**
     * Attach a social identity to the signed-in account.
     *
     * POST /api/auth/link  { provider, id_token, name?, avatar? }
     *
     * Called after Sign in with Apple or Google while already holding an
     * anonymous token. Two outcomes:
     *
     *   * Nobody owns that identity yet — the current row is upgraded in place,
     *     keeping its `rc_user_id`, so anything bought anonymously stays.
     *   * Someone does — this is the same person returning on a new device. We
     *     hand back the established account. If the anonymous side is carrying
     *     a subscription and the established side is not, its `rc_user_id` is
     *     moved across first, because the entitlement must follow the person.
     */
    public function link(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:google,apple'],
            'id_token' => ['required', 'string'],
            // Cosmetic only. Who you are comes from the verified token below;
            // these just fill the profile in when the provider supplies them.
            'name' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'string', 'max:255'],
        ]);

        // The identity is established here and nowhere else. This endpoint used
        // to take provider_id and email from the request body and trust them,
        // which meant posting a known email address returned a token for that
        // person's account.
        try {
            $identity = SocialIdentity::verify($validated['provider'], $validated['id_token']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        $current = $request->user();
        $column = $identity->provider === 'google' ? 'google_id' : 'apple_id';

        $existing = User::where($column, $identity->id)
            ->when($identity->email !== null, function ($q) use ($identity) {
                $q->orWhere('email', $identity->email);
            })
            ->where('id', '!=', $current->id)
            ->first();

        if (!$existing) {
            $current->fill(array_filter([
                $column => $identity->id,
                'email' => $identity->email,
                'name' => $validated['name'] ?? $identity->name,
                'avatar' => $validated['avatar'] ?? null,
            ], fn ($v) => $v !== null));
            $current->auth_provider = $identity->provider;
            $current->last_seen_at = now();
            $current->save();

            return $this->issue($current, 'account linked');
        }

        DB::transaction(function () use ($current, $existing, $column, $identity) {
            $currentHasSub = DB::table('subscriptions')
                ->where('user_id', $current->id)->where('is_active', true)->exists();
            $existingHasSub = DB::table('subscriptions')
                ->where('user_id', $existing->id)->where('is_active', true)->exists();

            // An entitlement must follow the person, not the row. Only move the
            // id when the established account has nothing to lose by it.
            if ($currentHasSub && !$existingHasSub) {
                $carried = $current->rc_user_id;
                $current->rc_user_id = null;
                $current->save();
                $existing->rc_user_id = $carried;
                DB::table('subscriptions')->where('user_id', $current->id)
                    ->update(['user_id' => $existing->id]);
            }

            $existing->{$column} = $identity->id;
            $existing->auth_provider = $identity->provider;
            $existing->install_id = $current->install_id ?: $existing->install_id;
            $existing->last_seen_at = now();
            $existing->save();

            // The anonymous shell has served its purpose. Its history, if any,
            // moves with it rather than being stranded.
            DB::table('translations')->where('user_id', $current->id)
                ->update(['user_id' => $existing->id]);
            DB::table('scan_attempts')->where('user_id', $current->id)
                ->update(['user_id' => $existing->id]);

            $current->tokens()->delete();
            if ($current->auth_provider === 'anonymous') {
                $current->delete();
            }
        });

        return $this->issue($existing->fresh(), 'signed in to your existing account');
    }

    /**
     * An opaque id for RevenueCat. Deliberately not the primary key: it is
     * visible in a third-party dashboard and should not leak row counts.
     */
    private function mintRcUserId(): string
    {
        do {
            $id = 'rc_' . Str::lower(Str::random(29));
        } while (User::where('rc_user_id', $id)->exists());

        return $id;
    }

    private function issue(User $user, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'recognised' => true,
            'user' => $user,
            'rc_user_id' => $user->rc_user_id,
            'token' => $user->createToken('mobile')->plainTextToken,
        ]);
    }
}
