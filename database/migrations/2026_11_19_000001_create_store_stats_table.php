<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily numbers from the stores (impressions, store-page views,
     * downloads), filled by `php artisan store:sync`.
     *
     * One row per report file, day, metric and country. `source` names the
     * file the number came from: a re-import replaces that file's rows, and
     * a day split across two files (Apple does this) still adds up right.
     */
    public function up(): void
    {
        Schema::create('store_stats', function (Blueprint $table) {
            $table->id();
            $table->string('source', 96);
            $table->date('date');
            $table->string('platform', 16);
            $table->string('metric', 32);
            // ISO code, or '' when the store does not say.
            $table->string('country', 2)->default('');
            $table->unsignedInteger('value');
            $table->timestamp('updated_at')->nullable();

            $table->unique(['source', 'date', 'platform', 'metric', 'country'], 'store_stats_unique');
            $table->index(['platform', 'metric', 'date']);
        });

        // The dashboard reads events by date range; without these every card
        // scans the whole table.
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex(['name', 'created_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::dropIfExists('store_stats');
    }
};
