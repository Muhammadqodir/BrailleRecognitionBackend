<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RevenueCat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the app reads to decide whether to open the paywall.
 *
 * `can_translate` is the only field the client needs. Everything else is for
 * display, and none of it is trusted from the client's side.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly RevenueCat $revenueCat)
    {
    }

    /**
     * GET /api/subscription
     *
     * A stored "not premium" is always re-checked against RevenueCat. We only
     * learn about a purchase when something tells us — the app's sync or a
     * webhook — and both can miss one: app versions before 3.1.2 sync a
     * purchase made on the anonymous RevenueCat id *before* RevenueCat merges
     * it into the account, which writes a fresh "not premium" row. People in a
     * paid trial were then shown the paywall indefinitely. Only non-premium
     * reads pay for the extra call, and they are about to see a paywall anyway.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $sub = $user->subscription()->first();

        if (!$sub || !$sub->grantsAccess()) {
            $this->revenueCat->sync($user);
            $user = $user->fresh();
        }

        return response()->json(['data' => $this->payload($user)]);
    }

    /**
     * POST /api/subscription/sync
     *
     * Re-reads RevenueCat rather than waiting for the webhook. Called straight
     * after a purchase or restore, because waiting would leave someone staring
     * at the paywall they just paid to dismiss.
     */
    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->revenueCat->sync($user);

        return response()->json(['data' => $this->payload($user->fresh())]);
    }

    private function payload($user): array
    {
        $sub = $user->subscription()->first();
        $isPremium = $sub !== null && $sub->grantsAccess();

        return [
            'rc_user_id' => $user->rc_user_id,
            'entitlement' => config('services.revenuecat.entitlement', 'Premium'),
            'is_premium' => $isPremium,
            'is_trial' => $isPremium && $sub->isTrial(),
            'status' => $sub->status ?? 'inactive',
            'product_id' => $sub->product_id ?? null,
            'store' => $sub->store ?? null,
            'will_renew' => (bool) ($sub->will_renew ?? false),
            'expires_at' => optional($sub?->expires_at)->toIso8601String(),
            // There is no free quota any more: the model is a trial, then pay.
            // The field stays so older clients reading it keep working.
            'can_translate' => $isPremium,
            'free_limit' => 0,
            'free_left' => 0,
        ];
    }
}
