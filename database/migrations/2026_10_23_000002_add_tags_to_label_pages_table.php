<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What makes a labeled page hard (a two-sided sheet, light from below or the
 * side), so the next model can be measured on each case on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_pages', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('edits');
        });
    }

    public function down(): void
    {
        Schema::table('label_pages', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
