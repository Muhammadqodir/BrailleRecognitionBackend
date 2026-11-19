<?php

namespace App\Services\Stores;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Saves one report file's numbers into `store_stats`, replacing whatever that
 * file gave last time. Re-running a sync is therefore harmless.
 */
class StoreStatsWriter
{
    /**
     * @param  array<string, int>  $totals  keyed "date|metric|country"
     * @return int rows written
     */
    public function replace(string $source, string $platform, array $totals): int
    {
        $now = now();
        $rows = [];

        foreach ($totals as $key => $value) {
            [$date, $metric, $country] = explode('|', $key);
            $rows[] = [
                'source' => $source,
                'date' => $date,
                'platform' => $platform,
                'metric' => $metric,
                'country' => $country,
                'value' => max(0, (int) $value),
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($source, $platform, $rows) {
            DB::table('store_stats')->where('source', $source)->where('platform', $platform)->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('store_stats')->insert($chunk);
            }
        });

        return count($rows);
    }

    /** For Http::retry(): try again after a dropped connection, a rate limit or a server error, not after a 4xx answer. */
    public static function worthRetrying(Throwable $e): bool
    {
        return !$e instanceof RequestException || $e->response->serverError() || $e->response->status() === 429;
    }

    /** Adds $value under date, metric and country. */
    public static function add(array &$totals, string $date, string $metric, string $country, int $value): void
    {
        $country = preg_match('/^[A-Z]{2}$/', $country) && $country !== 'ZZ' ? $country : '';
        $key = "{$date}|{$metric}|{$country}";
        $totals[$key] = ($totals[$key] ?? 0) + $value;
    }
}
