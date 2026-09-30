<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The app rates a scan 👍 = 1 or 👎 = -1. The column was unsigned and the
 * validation 1..5, so every thumbs-down was rejected and lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->tinyInteger('rating')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->change();
        });
    }
};
