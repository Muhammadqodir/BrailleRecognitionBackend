<?php

namespace App\Services\Stores;

use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Daily Google Play numbers from the Play Console's monthly CSV exports in
 * its Cloud Storage bucket (UTF-16, rewritten as the month goes on, about a
 * week behind).
 *
 *   page_views  store_performance …_country.csv, "Store listing visitors"
 *   downloads   installs …_country.csv, "Daily User Installs"
 *
 * Google exports no impressions, so the Play funnel starts at the store page.
 */
class GooglePlayStats
{
    private ?string $token = null;

    public function __construct(private readonly StoreStatsWriter $writer)
    {
    }

    public static function configured(): bool
    {
        $path = (string) config('services.google_play.service_account_path');

        return $path !== '' && is_readable($path) && config('services.google_play.stats_bucket');
    }

    /**
     * Imports this month's files and the $months - 1 months before it.
     *
     * @return array{files: int, rows: int}
     */
    public function sync(int $months): array
    {
        $package = config('services.google_play.package');
        $files = 0;
        $rows = 0;

        foreach (range(0, max(1, $months) - 1) as $back) {
            $month = Carbon::now()->startOfMonth()->subMonthsNoOverflow($back)->format('Ym');

            $reports = [
                "stats/store_performance/store_performance_{$package}_{$month}_country.csv" => ['Store listing visitors' => 'page_views'],
                "stats/installs/installs_{$package}_{$month}_country.csv" => ['Daily User Installs' => 'downloads'],
            ];

            foreach ($reports as $object => $metrics) {
                $csv = $this->download($object);
                if ($csv === null) {
                    continue;
                }

                $rows += $this->writer->replace('play:'.basename($object, '.csv'), 'android', $this->parse($csv, $metrics));
                $files++;
            }
        }

        return ['files' => $files, 'rows' => $rows];
    }

    /**
     * @param  array<string, string>  $metrics  CSV column => metric
     * @return array<string, int>
     */
    private function parse(string $csv, array $metrics): array
    {
        $lines = preg_split('/\r?\n/', trim($csv));
        $header = str_getcsv((string) array_shift($lines), ',', '"', '');
        $col = array_flip($header);
        $countryColumn = $col['Country'] ?? $col['Country / region'] ?? null;
        $totals = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $cells = str_getcsv($line, ',', '"', '');
            $date = $cells[$col['Date'] ?? -1] ?? '';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            // "Other" when Google hides small countries.
            $country = $countryColumn === null ? '' : strtoupper((string) ($cells[$countryColumn] ?? ''));

            foreach ($metrics as $column => $metric) {
                $value = (int) ($cells[$col[$column] ?? -1] ?? 0);
                if ($value > 0) {
                    StoreStatsWriter::add($totals, $date, $metric, $country, $value);
                }
            }
        }

        return $totals;
    }

    /** The object as UTF-8 text, or null when Google has not written it (yet). */
    private function download(string $object): ?string
    {
        $bucket = config('services.google_play.stats_bucket');
        $response = Http::withToken($this->token())
            ->timeout(60)
            ->retry(2, 1000, StoreStatsWriter::worthRetrying(...), throw: false)
            ->get("https://storage.googleapis.com/storage/v1/b/{$bucket}/o/".rawurlencode($object), ['alt' => 'media']);

        if ($response->status() === 404) {
            return null;
        }

        $body = $response->throw()->body();

        if (str_starts_with($body, "\xFF\xFE") || str_starts_with($body, "\xFE\xFF")) {
            $body = mb_convert_encoding($body, 'UTF-8', 'UTF-16');
        }

        return ltrim($body, "\xEF\xBB\xBF");
    }

    private function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $account = json_decode((string) file_get_contents(config('services.google_play.service_account_path')), true);
        if (!isset($account['client_email'], $account['private_key'])) {
            throw new RuntimeException('The Google Play service account file has no client_email or private_key.');
        }

        $assertion = JWT::encode([
            'iss' => $account['client_email'],
            'scope' => 'https://www.googleapis.com/auth/devstorage.read_only',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => time(),
            'exp' => time() + 3600,
        ], $account['private_key'], 'RS256');

        $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ])->throw();

        return $this->token = (string) $response->json('access_token');
    }
}
