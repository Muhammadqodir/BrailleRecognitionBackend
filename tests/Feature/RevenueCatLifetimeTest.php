<?php

namespace Tests\Feature;

use App\Services\RevenueCat;
use Tests\TestCase;

class RevenueCatLifetimeTest extends TestCase
{
    public function test_lifetime_grant_is_stored_within_timestamp_range(): void
    {
        $rc = app(RevenueCat::class);
        $entitlement = config('services.revenuecat.entitlement', 'Premium');
        $state = $rc->stateFromSubscriber([
            'entitlements' => [$entitlement => [
                'expires_date' => '2226-09-29T09:29:54Z',
                'purchase_date' => '2026-09-29T09:29:54Z',
                'product_identifier' => 'rc_promo_Premium_lifetime',
            ]],
            'subscriptions' => [],
        ]);

        $this->assertTrue($state['is_active']);
        $this->assertSame('2037-12-31', $state['expires_at']->toDateString());
    }
}
