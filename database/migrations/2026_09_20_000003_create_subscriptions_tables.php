<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entitlement state, and the raw RevenueCat webhook log behind it.
 *
 * RevenueCat is the source of truth for what was bought; this is our cached
 * view of it so that an entitlement check is one indexed read rather than an
 * HTTP call on the critical path of a scan.
 *
 * Every timestamp here is UTC, because RevenueCat speaks UTC and the legacy
 * backend's dependence on `date.timezone` was a live corruption risk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rc_user_id', 64)->index();
            $table->string('entitlement', 64)->default('Premium');

            $table->string('product_id')->nullable();
            $table->string('store', 32)->nullable();

            // RevenueCat's period_type: TRIAL, INTRO or NORMAL. This is how we
            // tell a trial start from a purchase — they convert on different
            // clocks and must never be counted as the same event.
            $table->string('period_type', 32)->nullable();

            $table->string('status', 32)->default('inactive');
            $table->boolean('is_active')->default(false);
            $table->boolean('will_renew')->default(false);
            $table->string('environment', 32)->default('PRODUCTION');
            $table->string('original_transaction_id')->nullable();

            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('unsubscribe_detected_at')->nullable();
            $table->timestamp('billing_issue_detected_at')->nullable();
            $table->timestamps();

            // One entitlement per user. The upsert target.
            $table->unique(['user_id', 'entitlement']);
            // Drives the "who is expiring" queries and the grace-period check.
            $table->index(['is_active', 'expires_at']);
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();

            // RevenueCat's own event id. UNIQUE is the idempotency guard —
            // webhooks are delivered at least once, and a replayed
            // INITIAL_PURCHASE must not be processed twice.
            $table->string('event_id')->unique();

            // Nullable rather than -1: an event can arrive for an app user id
            // we have never seen, and a sentinel would quietly join to nothing.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rc_user_id', 64)->index();

            $table->string('type', 64)->index();
            $table->string('product_id')->nullable();
            $table->string('store', 32)->nullable();
            $table->string('environment', 32)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscriptions');
    }
};
