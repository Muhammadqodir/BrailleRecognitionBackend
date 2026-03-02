<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log for Apple server-to-server notifications.
 *
 * Storing every incoming event allows replaying / re-processing if needed
 * and satisfies Apple's requirement to handle account-delete events reliably.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apple_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50)->index();   // consent-revoked, account-delete, …
            $table->string('apple_sub', 255)->nullable()->index(); // Apple user identifier
            $table->timestamp('event_time')->nullable();           // event_time from Apple payload
            $table->json('payload');                               // full decoded payload for auditing
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apple_notifications');
    }
};
