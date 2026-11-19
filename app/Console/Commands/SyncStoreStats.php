<?php

namespace App\Console\Commands;

use App\Services\Stores\AppStoreAnalytics;
use App\Services\Stores\GooglePlayStats;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pulls daily impressions, store-page views and downloads from App Store
 * Connect and the Play Console into `store_stats`, for the dashboard funnel.
 * Read-only on both stores. Run it once a day; the first run can reach back
 * further with --days and --months.
 */
class SyncStoreStats extends Command
{
    protected $signature = 'store:sync
        {--days=10 : App Store report files processed in the last N days (Apple keeps about 30)}
        {--months=2 : Google Play monthly files, counting back from this month}';

    protected $description = 'Import daily store impressions, page views and downloads for the dashboard';

    public function handle(AppStoreAnalytics $appStore, GooglePlayStats $googlePlay): int
    {
        $failed = false;

        $stores = [
            'App Store' => [AppStoreAnalytics::configured(), fn () => $appStore->sync((int) $this->option('days'))],
            'Google Play' => [GooglePlayStats::configured(), fn () => $googlePlay->sync((int) $this->option('months'))],
        ];

        foreach ($stores as $name => [$configured, $sync]) {
            if (!$configured) {
                $this->warn("{$name}: not configured, skipped.");
                continue;
            }

            try {
                $result = $sync();
                $this->info("{$name}: {$result['files']} files, {$result['rows']} rows.");
            } catch (Throwable $e) {
                // One store failing must not stop the other.
                $failed = true;
                $this->error("{$name}: ".$e->getMessage());
                report($e);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
