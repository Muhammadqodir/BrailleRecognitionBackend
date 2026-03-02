<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Apple Server-to-Server Notification Endpoint
 *
 * Apple sends signed JWTs (JWS) to this endpoint for the following events:
 *  - email-disabled     : user disabled Private Relay email forwarding
 *  - email-enabled      : user re-enabled Private Relay email forwarding
 *  - consent-revoked    : user revoked consent for your app (Stop Using Apple ID)
 *  - account-delete     : user permanently deleted their Apple ID
 *
 * Reference: https://developer.apple.com/documentation/sign_in_with_apple/processing_changes_for_sign_in_with_apple_accounts
 */
class AppleNotificationController extends Controller
{
    /**
     * Apple's JWKS endpoint used to verify the notification JWT signature.
     */
    private const APPLE_JWKS_URL = 'https://appleid.apple.com/auth/keys';

    /**
     * Expected JWT issuer.
     */
    private const APPLE_ISSUER = 'https://appleid.apple.com';

    /**
     * Handle incoming Apple server-to-server notification.
     *
     * Apple sends a form-encoded POST with a single `payload` field
     * containing a signed JWT.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        $rawPayload = $request->input('payload');

        if (empty($rawPayload)) {
            Log::warning('Apple notification received with no payload.');
            return response()->json(['error' => 'Missing payload'], 400);
        }

        try {
            $claims = $this->verifyAndDecode($rawPayload);
        } catch (\Throwable $e) {
            Log::error('Apple notification JWT verification failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // `events` is a JSON-encoded string inside the JWT
        $events = json_decode($claims->events ?? '{}', true);

        if (empty($events) || !isset($events['type'])) {
            Log::warning('Apple notification: missing or malformed events claim.', ['claims' => (array) $claims]);
            return response()->json(['error' => 'Malformed events'], 400);
        }

        // Log the raw notification for auditing
        $this->logNotification($events, (array) $claims);

        $appleUserId = $events['sub'] ?? null;
        $eventType   = $events['type'];

        Log::info("Apple notification received: {$eventType}", ['sub' => $appleUserId]);

        match ($eventType) {
            'email-disabled'   => $this->handleEmailDisabled($appleUserId, $events),
            'email-enabled'    => $this->handleEmailEnabled($appleUserId, $events),
            'consent-revoked'  => $this->handleConsentRevoked($appleUserId),
            'account-delete'   => $this->handleAccountDelete($appleUserId),
            default            => Log::info("Apple notification: unhandled event type [{$eventType}]."),
        };

        // Apple expects a 200 OK with an empty body
        return response()->noContent();
    }

    // -------------------------------------------------------------------------
    // Event Handlers
    // -------------------------------------------------------------------------

    /**
     * User disabled Private Relay email forwarding for your app.
     * You can no longer send emails to their relay address.
     */
    private function handleEmailDisabled(string $appleUserId, array $events): void
    {
        $user = $this->findUserByAppleId($appleUserId);
        if (!$user) {
            return;
        }

        Log::info("Apple: email relay disabled for user #{$user->id}");
        // Optionally flag or notify internally – do NOT bounce emails.
    }

    /**
     * User re-enabled Private Relay email forwarding for your app.
     */
    private function handleEmailEnabled(string $appleUserId, array $events): void
    {
        $email = $events['email'] ?? null;
        $user  = $this->findUserByAppleId($appleUserId);

        if (!$user) {
            return;
        }

        Log::info("Apple: email relay re-enabled for user #{$user->id}", compact('email'));
    }

    /**
     * User selected "Stop Using Apple ID" for your app (revoked consent).
     * Treat this as a logout / account deactivation – do NOT delete data yet.
     * Apple may still send an account-delete event separately.
     */
    private function handleConsentRevoked(string $appleUserId): void
    {
        $user = $this->findUserByAppleId($appleUserId);
        if (!$user) {
            return;
        }

        // Revoke all access tokens so the user is effectively signed out
        $user->tokens()->delete();

        Log::info("Apple: consent revoked → tokens deleted for user #{$user->id}");
    }

    /**
     * User permanently deleted their Apple ID.
     * You are required to delete their account and all associated data.
     */
    private function handleAccountDelete(string $appleUserId): void
    {
        $user = $this->findUserByAppleId($appleUserId);
        if (!$user) {
            Log::warning("Apple account-delete: no local user found for apple_id [{$appleUserId}]");
            return;
        }

        // Revoke tokens first
        $user->tokens()->delete();

        // Hard-delete the user record (adjust to soft-delete if preferred)
        $userId = $user->id;
        $user->delete();

        Log::info("Apple: account permanently deleted for user #{$userId} (apple_id: {$appleUserId})");
    }

    // -------------------------------------------------------------------------
    // JWT Verification
    // -------------------------------------------------------------------------

    /**
     * Fetch Apple's public JWKS, decode, and verify the notification JWT.
     *
     * @throws \RuntimeException|\Firebase\JWT\ExpiredException|\Firebase\JWT\SignatureInvalidException
     */
    private function verifyAndDecode(string $jwt): object
    {
        $response = Http::timeout(5)->get(self::APPLE_JWKS_URL);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to fetch Apple JWKS.');
        }

        $jwks    = $response->json();
        $keySet  = JWK::parseKeySet($jwks);

        $decoded = JWT::decode($jwt, $keySet);

        if (($decoded->iss ?? '') !== self::APPLE_ISSUER) {
            throw new \RuntimeException('JWT issuer mismatch.');
        }

        $expectedAudiences = array_filter([
            config('services.apple.client_id'),
            env('APPLE_CLIENT_ID'),
        ]);

        $aud = is_array($decoded->aud) ? $decoded->aud : [$decoded->aud];

        if (!empty($expectedAudiences) && empty(array_intersect($aud, $expectedAudiences))) {
            throw new \RuntimeException('JWT audience mismatch.');
        }

        return $decoded;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function findUserByAppleId(string $appleUserId): ?User
    {
        return User::where('apple_id', $appleUserId)->first();
    }

    /**
     * Persist every notification for audit / replay purposes.
     */
    private function logNotification(array $events, array $claims): void
    {
        try {
            DB::table('apple_notifications')->insert([
                'event_type'   => $events['type'] ?? 'unknown',
                'apple_sub'    => $events['sub'] ?? null,
                'event_time'   => isset($events['event_time'])
                    ? \Carbon\Carbon::createFromTimestampMs($events['event_time'])
                    : null,
                'payload'      => json_encode(['events' => $events, 'claims' => $claims]),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // Never let logging break the notification response
            Log::error('Apple notification log failed: ' . $e->getMessage());
        }
    }
}
