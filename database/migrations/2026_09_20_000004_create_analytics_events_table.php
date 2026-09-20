<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The funnel log: every tracked action from first app open to renewal.
 *
 * `install_id` is the spine. It is minted on the device before any account
 * exists and travels on every event, which is what lets a funnel be followed
 * from a first launch, through an anonymous scan and a sign-in, to a renewal
 * eleven months later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Client-generated. UNIQUE because the app batches events and
            // retries failed batches — without this a bad connection would
            // inflate every step of the funnel.
            $table->string('event_uid', 64)->unique();

            $table->string('install_id', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 64);
            $table->json('props')->nullable();

            $table->string('platform', 16)->nullable();
            $table->string('app_version', 32)->nullable();
            $table->boolean('is_premium')->default(false);
            $table->string('locale', 16)->nullable();

            // 'client' or 'webhook'. Revenue events arrive server-side from
            // RevenueCat and belong in the same funnel as the taps that caused
            // them, but they are not device-reported and should be separable.
            $table->string('source', 16)->default('client');

            // When it happened on the device vs when we received it. Events
            // queue up offline, so these can be hours apart and only the first
            // orders a funnel correctly.
            $table->timestamp('client_ts')->nullable();
            $table->timestamps();

            $table->index(['name', 'client_ts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
