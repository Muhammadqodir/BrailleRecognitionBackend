<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $keys;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-19 05:00:00'));
        Http::preventStrayRequests();

        // Throwaway keys, so the JWTs can be signed without the real ones.
        $this->keys = storage_path('framework/testing/store-keys-'.Str::random(8));
        File::ensureDirectoryExists($this->keys);

        openssl_pkey_export(openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']), $ecKey);
        File::put("{$this->keys}/asc.p8", $ecKey);
        openssl_pkey_export(openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]), $rsaKey);
        File::put("{$this->keys}/play.json", json_encode(['client_email' => 'stats@example.iam.gserviceaccount.com', 'private_key' => $rsaKey]));

        config([
            'services.app_store_connect' => ['key_id' => 'KEY123', 'issuer_id' => 'issuer', 'private_key_path' => "{$this->keys}/asc.p8", 'app_id' => '1669110413'],
            'services.google_play' => ['service_account_path' => "{$this->keys}/play.json", 'stats_bucket' => 'pubsite_prod_test', 'package' => 'uz.mq.braillerecognition'],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->keys);
        parent::tearDown();
    }

    public function test_store_numbers_are_imported_and_a_rerun_replaces_them(): void
    {
        $discovery = implode("\n", [
            "Date\tApp Name\tEvent\tPage Type\tTerritory\tCounts",
            "2026-11-17\tBraille\tImpression\tProduct page\tUS\t10",
            "2026-11-17\tBraille\tImpression\tNo page\tZZ\t3",
            "2026-11-17\tBraille\tPage view\tProduct page\tUS\t4",
            "2026-11-17\tBraille\tPage view\tStore sheet\tDE\t2",
            "2026-11-17\tBraille\tPage view\tEvent page\tUS\t99",
            "2026-11-17\tBraille\tTap\tProduct page\tUS\t50",
        ]);
        $downloads = implode("\n", [
            "Date\tDownload Type\tTerritory\tCounts",
            "2026-11-17\tFirst-time download\tUS\t5",
            "2026-11-17\tRedownload\tUS\t7",
        ]);
        $visitors = "Date,Package Name,Country,Store listing acquisitions,Store listing visitors\n"
            ."2026-11-05,uz.mq.braillerecognition,Other,3,30\n"
            ."2026-11-06,uz.mq.braillerecognition,Other,2,25\n";
        $installs = "Date,Package Name,Country,Daily Device Installs,Daily User Installs\n"
            ."2026-11-05,uz.mq.braillerecognition,US,6,4\n"
            ."2026-11-05,uz.mq.braillerecognition,BR,2,2\n"
            ."2026-11-06,uz.mq.braillerecognition,BR,0,0\n";

        Http::fake(function (Request $request) use ($discovery, $downloads, $visitors, $installs) {
            $url = urldecode($request->url());

            return match (true) {
                str_contains($url, '/v1/apps/1669110413/analyticsReportRequests') => Http::response(['data' => [
                    ['id' => 'stopped', 'attributes' => ['stoppedDueToInactivity' => true]],
                    ['id' => 'req-1', 'attributes' => ['stoppedDueToInactivity' => false]],
                ]]),
                str_contains($url, '/v1/analyticsReportRequests/req-1/reports') => Http::response(['data' => [
                    ['id' => str_contains($url, 'App Downloads Standard') ? 'rep-dl' : 'rep-disc'],
                ]]),
                // Two pages of instances; the old one is outside --days.
                str_contains($url, '/v1/analyticsReports/rep-disc/instances') && str_contains($url, 'cursor=2') => Http::response(['data' => [
                    ['id' => 'inst-old', 'attributes' => ['processingDate' => '2026-10-01']],
                ]]),
                str_contains($url, '/v1/analyticsReports/rep-disc/instances') => Http::response([
                    'data' => [['id' => 'inst-disc', 'attributes' => ['processingDate' => '2026-11-18']]],
                    'links' => ['next' => 'https://api.appstoreconnect.apple.com/v1/analyticsReports/rep-disc/instances?cursor=2'],
                ]),
                str_contains($url, '/v1/analyticsReports/rep-dl/instances') => Http::response(['data' => [
                    ['id' => 'inst-dl', 'attributes' => ['processingDate' => '2026-11-18']],
                ]]),
                str_contains($url, '/v1/analyticsReportInstances/inst-disc/segments') => Http::response(['data' => [['attributes' => ['url' => 'https://s3.example.test/disc.gz']]]]),
                str_contains($url, '/v1/analyticsReportInstances/inst-dl/segments') => Http::response(['data' => [['attributes' => ['url' => 'https://s3.example.test/dl.gz']]]]),
                $url === 'https://s3.example.test/disc.gz' => Http::response(gzencode($discovery)),
                $url === 'https://s3.example.test/dl.gz' => Http::response(gzencode($downloads)),
                str_contains($url, 'oauth2.googleapis.com/token') => Http::response(['access_token' => 'play-token']),
                // Google writes these as UTF-16; the installs one plain, to cover both.
                str_contains($url, 'store_performance_uz.mq.braillerecognition_202611_country.csv') => Http::response("\xFF\xFE".mb_convert_encoding($visitors, 'UTF-16LE', 'UTF-8')),
                str_contains($url, 'installs_uz.mq.braillerecognition_202611_country.csv') => Http::response($installs),
                str_contains($url, 'storage.googleapis.com') => Http::response('Not found', 404),
                default => throw new \RuntimeException("Unexpected request: {$url}"),
            };
        });

        $this->artisan('store:sync', ['--days' => 10, '--months' => 2])
            ->expectsOutputToContain('App Store: 2 files, 5 rows.')
            ->expectsOutputToContain('Google Play: 2 files, 4 rows.')
            ->assertSuccessful();

        $rows = fn () => DB::table('store_stats')
            ->orderBy('platform')->orderBy('date')->orderBy('metric')->orderBy('country')
            ->get(['platform', 'date', 'metric', 'country', 'value'])
            ->map(fn ($row) => implode(' ', [$row->platform, $row->date, $row->metric, $row->country ?: '--', $row->value]))
            ->all();

        $expected = [
            'android 2026-11-05 downloads BR 2',
            'android 2026-11-05 downloads US 4',
            'android 2026-11-05 page_views -- 30',
            'android 2026-11-06 page_views -- 25',
            'ios 2026-11-17 downloads US 5',
            'ios 2026-11-17 impressions -- 3',
            'ios 2026-11-17 impressions US 10',
            'ios 2026-11-17 page_views DE 2',
            'ios 2026-11-17 page_views US 4',
        ];
        $this->assertSame($expected, $rows());

        // Running again rewrites each file's rows rather than adding to them.
        $this->artisan('store:sync', ['--days' => 10, '--months' => 2])->assertSuccessful();
        $this->assertSame($expected, $rows());
    }

    public function test_a_store_without_credentials_is_skipped(): void
    {
        config(['services.app_store_connect.private_key_path' => '', 'services.google_play.service_account_path' => '']);

        $this->artisan('store:sync')
            ->expectsOutputToContain('App Store: not configured, skipped.')
            ->expectsOutputToContain('Google Play: not configured, skipped.')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_one_store_failing_still_imports_the_other(): void
    {
        Http::fake([
            'api.appstoreconnect.apple.com/*' => Http::response(['errors' => [['status' => '401']]], 401),
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'play-token']),
            'storage.googleapis.com/*' => Http::response("Date,Country,Daily User Installs\n2026-11-05,US,4\n"),
        ]);

        $this->artisan('store:sync', ['--months' => 1])
            ->expectsOutputToContain('Google Play: 2 files')
            ->assertFailed();

        $this->assertSame(4, (int) DB::table('store_stats')->where('platform', 'android')->where('metric', 'downloads')->sum('value'));
    }
}
