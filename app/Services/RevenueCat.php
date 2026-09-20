<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to RevenueCat, and turns what it says into a `subscriptions` row.
 *
 * The app is never trusted about entitlement: it reports what the store told
 * it, but access is decided here, from data we fetched ourselves. A patched
 * client cannot grant itself premium.
 *
 * Every timestamp RevenueCat sends is UTC. They are stored as UTC and compared
 * as UTC — the legacy backend's dependence on the server's `date.timezone` was
 * a live data-corruption risk and is not reproduced here.
 */
class RevenueCat
{
    public function __construct(
        private readonly ?string $secretKey = null,
        private readonly string $entitlement = 'Premium',
    ) {
    }

    private function key(): string
    {
        return $this->secretKey ?? (string) config('services.revenuecat.secret_key');
    }

    private function entitlementId(): string
    {
        return (string) config('services.revenuecat.entitlement', $this->entitlement);
    }

    /**
     * Authoritative read for one user. Returns the decoded `subscriber` object,
     * or null if RevenueCat could not be reached or does not know them.
     */
    public function fetchSubscriber(string $rcUserId): ?array
    {
        if ($this->key() === '' || $rcUserId === '') {
            return null;
        }

        try {
            $response = Http::withToken($this->key())
                ->acceptJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->get('https://api.revenuecat.com/v1/subscribers/' . rawurlencode($rcUserId));
        } catch (\Throwable $e) {
            Log::warning('RevenueCat fetch failed', ['rc_user_id' => $rcUserId, 'error' => $e->getMessage()]);
            return null;
        }

        if (!$response->successful()) {
            Log::warning('RevenueCat returned an error', [
                'rc_user_id' => $rcUserId, 'status' => $response->status(),
            ]);
            return null;
        }

        return $response->json('subscriber');
    }

    /**
     * Flattens a subscriber payload into `subscriptions` columns.
     *
     * A user with no entitlement object has simply never purchased; that is a
     * valid state worth recording, not a failure.
     */
    public function stateFromSubscriber(?array $subscriber): array
    {
        $inactive = [
            'is_active' => false, 'status' => 'inactive', 'product_id' => null,
            'store' => null, 'period_type' => null, 'will_renew' => false,
            'environment' => 'PRODUCTION', 'original_transaction_id' => null,
            'purchased_at' => null, 'expires_at' => null,
            'unsubscribe_detected_at' => null, 'billing_issue_detected_at' => null,
        ];

        if (!is_array($subscriber)) {
            return $inactive;
        }

        $entitlement = $subscriber['entitlements'][$this->entitlementId()] ?? null;
        if (!is_array($entitlement)) {
            return $inactive;
        }

        $productId = $entitlement['product_identifier'] ?? '';
        $purchase = $subscriber['subscriptions'][$productId] ?? [];

        $expiresAt = $this->toUtc($entitlement['expires_date'] ?? null);
        $unsubscribed = $this->toUtc($purchase['unsubscribe_detected_at'] ?? null);
        $billingIssue = $this->toUtc($purchase['billing_issues_detected_at'] ?? null);

        $isActive = $expiresAt === null || $expiresAt->addHour()->isFuture();

        if ($isActive) {
            $status = $billingIssue !== null ? 'billing_issue'
                : ($unsubscribed !== null ? 'cancelled' : 'active');
        } else {
            $status = $unsubscribed !== null ? 'cancelled' : 'expired';
        }

        return [
            'is_active' => $isActive,
            'status' => $status,
            'product_id' => $productId ?: null,
            'store' => $purchase['store'] ?? null,
            'period_type' => $purchase['period_type'] ?? null,
            'will_renew' => $isActive && $unsubscribed === null && $billingIssue === null,
            'environment' => !empty($purchase['is_sandbox']) ? 'SANDBOX' : 'PRODUCTION',
            'original_transaction_id' => $purchase['original_purchase_date'] ?? null,
            'purchased_at' => $this->toUtc($entitlement['purchase_date'] ?? null),
            'expires_at' => $this->toUtc($entitlement['expires_date'] ?? null),
            'unsubscribe_detected_at' => $unsubscribed,
            'billing_issue_detected_at' => $billingIssue,
        ];
    }

    /** Re-reads RevenueCat and writes the result. Returns the stored row. */
    public function sync(User $user): ?Subscription
    {
        if (!$user->rc_user_id) {
            return null;
        }

        $state = $this->stateFromSubscriber($this->fetchSubscriber($user->rc_user_id));

        return Subscription::updateOrCreate(
            ['user_id' => $user->id, 'entitlement' => $this->entitlementId()],
            $state + ['rc_user_id' => $user->rc_user_id],
        );
    }

    /**
     * RevenueCat sends epoch milliseconds on webhooks and ISO-8601 strings on
     * the REST API, so both have to be accepted.
     */
    public function toUtc(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::createFromTimestampMs((int) $value, 'UTC');
            }
            return Carbon::parse((string) $value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
