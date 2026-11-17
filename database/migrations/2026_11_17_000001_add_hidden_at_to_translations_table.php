<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A scan the person removed from their history. The row stays: free scans
 * are counted from history, so deleting the row would hand the scan back,
 * and the admin pages and the labeling queue still refer to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
        });
    }
};
