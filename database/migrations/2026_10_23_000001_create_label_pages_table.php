<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hand-checked cells for training the phone's detector and dot classifier.
 *
 * One row per phone scan in the labeling queue. The phone's own cells stay in
 * the translation's result_json; this keeps what the OCR host found on the
 * same photo (to suggest cells the phone missed) and, once an admin has been
 * through the page, the corrected cells. Deleting the scan deletes its labels,
 * as it deletes the photo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('translation_id')->unique()->constrained()->cascadeOnDelete();

            // "test" pages are never trained on: they measure the model on
            // people it has not seen. Split by user, so one person's pages
            // never straddle the two.
            $table->string('split', 8)->default('train');

            // todo -> done, or skipped (not braille, unreadable photo, ...).
            $table->string('status', 12)->default('todo');
            $table->string('skip_reason', 32)->nullable();

            // Higher first: pages where the phone was unsure or disagreed with
            // the server, so each labeled page teaches as much as possible.
            $table->float('priority')->default(0);
            $table->json('issues')->nullable();

            // [x1, y1, x2, y2, pattern, score] from the OCR host's model, in
            // photo pixels and the phone's box convention.
            $table->longText('suggestions')->nullable();

            // [x1, y1, x2, y2, pattern] as saved by the admin, in photo pixels.
            $table->longText('cells')->nullable();
            $table->json('edits')->nullable();

            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('seconds')->default(0);
            $table->timestamp('labeled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_pages');
    }
};
