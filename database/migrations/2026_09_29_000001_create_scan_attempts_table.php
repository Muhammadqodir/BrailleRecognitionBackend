<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the phone's model made of a scan it then handed to the OCR server.
 *
 * These are the pages the on-device model gets wrong (mostly printed or
 * on-screen dots), so they are the most valuable material for retraining it.
 * Kept apart from `translations` so they never show up in anyone's history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The server's result for the same photo, when it produced one.
            $table->foreignId('translation_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->string('lang', 8);

            // Why the phone's result was not used: few_cells | error.
            $table->string('reason', 16)->index();

            // Photo on the private disk, and the phone's cells
            // (same format as translations.result_json for phone scans).
            $table->string('photo')->nullable();
            $table->longText('result_json')->nullable();
            $table->string('error', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_attempts');
    }
};
