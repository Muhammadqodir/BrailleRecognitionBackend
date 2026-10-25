<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How far the photo was turned clockwise (0, 90, 180, 270) before its
     * cells were labeled: the cells are in the turned picture's pixels.
     */
    public function up(): void
    {
        Schema::table('label_pages', function (Blueprint $table) {
            $table->unsignedSmallInteger('rotation')->default(0)->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('label_pages', function (Blueprint $table) {
            $table->dropColumn('rotation');
        });
    }
};
