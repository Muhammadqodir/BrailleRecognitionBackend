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

    public function test_lifetime_purchase_reads_store_from_non_subscriptions(): void
    {
        $rc = app(RevenueCat::class);
        $entitlement = config('services.revenuecat.entitlement', 'Premium');
        $state = $rc->stateFromSubscriber([
            'entitlements' => [$entitlement => [
                'expires_date' => null,
                'purchase_date' => '2026-11-20T10:00:00Z',
                'product_identifier' => 'braille_premium_lifetime',
            ]],
            'subscriptions' => [],
            'non_subscriptions' => ['braille_premium_lifetime' => [[
                'id' => 'abc',
                'is_sandbox' => false,
                'purchase_date' => '2026-11-20T10:00:00Z',
                'store' => 'play_store',
                'store_transaction_id' => 'GPA.1234',
            ]]],
        ]);

        $this->assertTrue($state['is_active']);
        $this->assertSame('active', $state['status']);
        $this->assertNull($state['expires_at']);
        $this->assertFalse($state['will_renew']);
        $this->assertSame('play_store', $state['store']);
        $this->assertSame('GPA.1234', $state['original_transaction_id']);
        $this->assertSame('PRODUCTION', $state['environment']);
        $this->assertSame('braille_premium_lifetime', $state['product_id']);
    }
}
