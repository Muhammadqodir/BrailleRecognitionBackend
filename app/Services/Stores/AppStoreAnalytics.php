<?php

namespace App\Services\Stores;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Daily App Store numbers from App Store Connect's Analytics Reports API.
 *
 * Apple publishes the reports of an "ongoing" report request once a day, as
 * gzipped tab-separated files, about two days behind. Only daily instances
 * are read; Apple keeps roughly the last month of them, so the sync has to
 * run regularly to build up history.
 *
 *   impressions  "App Store Discovery and Engagement Standard", Event = Impression
 *   page_views   same report, Event = Page view on the product page or store sheet
 *   downloads    "App Downloads Standard", Download Type = First-time download
 */
class AppStoreAnalytics
{
    private const API = 'https://api.appstoreconnect.apple.com';

    private const DISCOVERY = 'App Store Discovery and Engagement Standard';

    private const DOWNLOADS = 'App Downloads Standard';

    private ?string $token = null;

    public function __construct(private readonly StoreStatsWriter $writer)
    {
    }

    public static function configured(): bool
    {
        $path = (string) config('services.app_store_connect.private_key_path');

        return $path !== '' && is_readable($path)
            && config('services.app_store_connect.key_id') && config('services.app_store_connect.issuer_id');
    }

    /**
     * Imports the daily report files Apple processed in the last $days days.
     *
     * @return array{files: int, rows: int}
     */
    public function sync(int $days): array
    {
        $requestId = $this->ongoingRequestId();
        $since = now()->subDays($days)->toDateString();
        $files = 0;
        $rows = 0;

        foreach ([self::DISCOVERY, self::DOWNLOADS] as $reportName) {
            $reportId = $this->reportId($requestId, $reportName);

            foreach ($this->dailyInstances($reportId) as $instance) {
                if ($instance['processingDate'] < $since) {
                    continue;
                }

                $totals = [];
                foreach ($this->segmentUrls($instance['id']) as $url) {
                    $this->readSegment($url, $reportName, $totals);
                }

                $rows += $this->writer->replace("asc:{$instance['id']}", 'ios', $totals);
                $files++;
            }
        }

        return ['files' => $files, 'rows' => $rows];
    }

    private function ongoingRequestId(): string
    {
        $appId = config('services.app_store_connect.app_id');
        $requests = $this->get("/v1/apps/{$appId}/analyticsReportRequests", ['filter[accessType]' => 'ONGOING'])['data'] ?? [];

        foreach ($requests as $request) {
            if (!($request['attributes']['stoppedDueToInactivity'] ?? false)) {
                return $request['id'];
            }
        }

        // Creating one is a one-off step in App Store Connect; Apple then
        // takes a day or two to publish the first files.
        throw new RuntimeException('No active ONGOING analytics report request for this app in App Store Connect.');
    }

    private function reportId(string $requestId, string $name): string
    {
        $reports = $this->get("/v1/analyticsReportRequests/{$requestId}/reports", ['filter[name]' => $name])['data'] ?? [];

        return $reports[0]['id'] ?? throw new RuntimeException("App Store Connect has no report named \"{$name}\".");
    }

    /** @return list<array{id: string, processingDate: string}> */
    private function dailyInstances(string $reportId): array
    {
        $instances = [];
        $url = "/v1/analyticsReports/{$reportId}/instances";
        $query = ['filter[granularity]' => 'DAILY', 'limit' => 200];

        while ($url !== null) {
            $page = $this->get($url, $query);
            foreach ($page['data'] ?? [] as $instance) {
                $instances[] = ['id' => $instance['id'], 'processingDate' => (string) $instance['attributes']['processingDate']];
            }
            $url = $page['links']['next'] ?? null;
            $query = [];
        }

        return $instances;
    }

    /** @return list<string> */
    private function segmentUrls(string $instanceId): array
    {
        $segments = $this->get("/v1/analyticsReportInstances/{$instanceId}/segments")['data'] ?? [];

        return array_values(array_filter(array_map(fn (array $segment) => $segment['attributes']['url'] ?? null, $segments)));
    }

    private function readSegment(string $url, string $reportName, array &$totals): void
    {
        // A pre-signed S3 link: no Authorization header, or S3 refuses it.
        $body = Http::timeout(60)->retry(2, 1000, StoreStatsWriter::worthRetrying(...))->get($url)->throw()->body();
        $text = str_starts_with($body, "\x1f\x8b") ? gzdecode($body) : $body;

        if ($text === false) {
            throw new RuntimeException('Could not unzip an App Store Connect report file.');
        }

        $lines = preg_split('/\r?\n/', trim($text));
        $header = str_getcsv((string) array_shift($lines), "\t", '"', '');
        $col = array_flip($header);

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $cells = str_getcsv($line, "\t", '"', '');
            $value = fn (string $name) => $cells[$col[$name] ?? -1] ?? '';
            $date = $value('Date');
            $country = strtoupper($value('Territory'));
            $count = (int) $value('Counts');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $count <= 0) {
                continue;
            }

            if ($reportName === self::DISCOVERY) {
                $event = $value('Event');
                $pageType = $value('Page Type');

                if ($event === 'Impression') {
                    StoreStatsWriter::add($totals, $date, 'impressions', $country, $count);
                } elseif ($event === 'Page view' && in_array($pageType, ['Product page', 'Store sheet'], true)) {
                    StoreStatsWriter::add($totals, $date, 'page_views', $country, $count);
                }
            } elseif ($value('Download Type') === 'First-time download') {
                StoreStatsWriter::add($totals, $date, 'downloads', $country, $count);
            }
        }
    }

    private function get(string $path, array $query = []): array
    {
        $url = str_starts_with($path, 'http') ? $path : self::API.$path;

        // Any query, even an empty one, replaces the URL's own: a "next" link
        // would lose its cursor and load the first page again forever.
        $response = $query === [] ? $this->client()->get($url) : $this->client()->get($url, $query);

        return $response->throw()->json() ?? [];
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->token())->acceptJson()->timeout(30)->retry(2, 1000, StoreStatsWriter::worthRetrying(...));
    }

    private function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $config = config('services.app_store_connect');
        $key = file_get_contents($config['private_key_path']);

        // Apple refuses tokens that live longer than 20 minutes.
        return $this->token = JWT::encode([
            'iss' => $config['issuer_id'],
            'iat' => time(),
            'exp' => time() + 15 * 60,
            'aud' => 'appstoreconnect-v1',
        ], $key, 'ES256', $config['key_id']);
    }
}
