<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's scan history.
 *
 * Carried over from the legacy `translations` table, with the string-typed ids
 * and the ambiguous `date` column replaced by real types. Full history is a
 * Premium feature, so this is read on every app open and paginated for free
 * users — hence the (user_id, created_at) index rather than user_id alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The Unicode braille the OCR read, and the plain text it decoded
            // to. `result` is empty when OCR found nothing — about one scan in
            // ten historically, which is worth being able to query for.
            $table->text('result_braille')->nullable();
            $table->text('result')->nullable();
            $table->longText('result_json')->nullable();

            // Paths on the OCR host, not this server.
            $table->string('input_file')->nullable();
            $table->string('result_marked')->nullable();

            $table->string('lang', 8)->index();
            $table->boolean('is_fav')->default(false);

            // 1..5 from the post-scan prompt; null until the user rates it.
            $table->unsignedTinyInteger('rating')->nullable();

            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
