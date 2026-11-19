<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Translation;
use App\Models\User;
use App\Support\Country;
use App\Support\Period;
use App\Support\Purchases;
use App\Support\Sql;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin overview. Every number is for the period picked at the top
 * (`?range=30d`, or `?from=…&to=…`) and is compared with the same number of
 * days right before it.
 *
 * "New users" are installs opened for the first time: the app sends
 * `app_opened` with is_first_open once per install. Events reach accounts
 * through the install ID only — analytics_events.user_id stays empty.
 */
class DashboardController extends Controller
{
    private const PLATFORMS = ['ios', 'android'];

    public function index(Request $request): Response
    {
        $period = Period::fromRequest($request);
        $previous = $period->previous();
        $purchases = Purchases::between($previous->from, $period->to);

        return Inertia::render('dashboard', [
            'period' => $period->toArray(),
            'kpis' => $this->kpis($period, $previous, $purchases),
            'growth' => $this->growth($period, $purchases),
            'translationVolume' => $this->translationVolume($period),
            'countries' => $this->countries($period, $previous, $purchases),
            'newUsersBy' => $this->newUsersBy($period, $previous),
            'funnel' => $this->funnel($period, $purchases),
            'brailleLanguages' => $this->brailleLanguages($period),
            'subscriptionEvents' => $this->recentSubscriptionEvents(),
            'recentUsers' => $this->recentUsers(),
        ]);
    }

    /**
     * The six headline numbers, each with the previous period, today and a
     * series for the sparkline.
     *
     * @return list<array<string, mixed>>
     */
    private function kpis(Period $period, Period $previous, Collection $purchases): array
    {
        $today = Period::preset('today');
        $buckets = $period->buckets();
        $now = $this->premiumCounts();

        // Distinct installs per day; a week's point is the average day.
        $activeDaily = $this->perDay($this->events($period), 'COUNT(DISTINCT install_id)');
        $installsDaily = $this->perDay($this->firstOpens($period), 'COUNT(DISTINCT install_id)');
        $translationsDaily = $this->perDay(DB::table('translations')->whereBetween('created_at', $period->range()), 'COUNT(*)');

        $inPeriod = Purchases::in($purchases, $period);
        $inPrevious = Purchases::in($purchases, $previous);
        $inToday = Purchases::in($purchases, $today);

        $purchaseSeries = function (callable $value) use ($inPeriod, $period, $buckets) {
            $byBucket = $inPeriod->groupBy(fn (array $row) => $period->bucketOf($row['date']));

            return array_map(fn (string $bucket) => round((float) $value($byBucket->get($bucket, collect())), 2), $buckets);
        };

        $revenue = fn (Collection $rows) => round((float) $rows->sum('revenue'), 2);
        $trials = fn (Collection $rows) => $rows->where('trial', true)->count();

        return [
            [
                'key' => 'active',
                'value' => $this->events($period)->distinct()->count('install_id'),
                'previous' => $this->events($previous)->distinct()->count('install_id'),
                'today' => $this->events($today)->distinct()->count('install_id'),
                'series' => $this->bucketed($activeDaily, $period, 'avg'),
            ],
            [
                'key' => 'newUsers',
                'value' => $this->firstOpens($period)->distinct()->count('install_id'),
                'previous' => $this->firstOpens($previous)->distinct()->count('install_id'),
                'today' => $this->firstOpens($today)->distinct()->count('install_id'),
                'series' => $this->bucketed($installsDaily, $period),
            ],
            [
                'key' => 'paid',
                'value' => Purchases::newPayers($inPeriod),
                'previous' => Purchases::newPayers($inPrevious),
                'today' => Purchases::newPayers($inToday),
                'series' => $purchaseSeries(fn (Collection $rows) => Purchases::newPayers($rows)),
                'now' => $now['paid'],
            ],
            [
                'key' => 'trials',
                'value' => $trials($inPeriod),
                'previous' => $trials($inPrevious),
                'today' => $trials($inToday),
                'series' => $purchaseSeries($trials),
                'now' => $now['trial'],
            ],
            [
                'key' => 'translations',
                'value' => array_sum($translationsDaily),
                'previous' => DB::table('translations')->whereBetween('created_at', $previous->range())->count(),
                'today' => DB::table('translations')->whereBetween('created_at', $today->range())->count(),
                'series' => $this->bucketed($translationsDaily, $period),
            ],
            [
                'key' => 'earnings',
                'value' => $revenue($inPeriod),
                'previous' => $revenue($inPrevious),
                'today' => $revenue($inToday),
                'series' => $purchaseSeries($revenue),
                'proceeds' => round((float) $inPeriod->sum('proceeds'), 2),
            ],
        ];
    }

    /**
     * New installs and trial starts per chart bucket, split by platform.
     *
     * @return list<array{date: string, installsIos: int, installsAndroid: int, trialsIos: int, trialsAndroid: int}>
     */
    private function growth(Period $period, Collection $purchases): array
    {
        $installs = $this->firstOpens($period)
            ->selectRaw('DATE(created_at) as day, platform, COUNT(DISTINCT install_id) as count')
            ->groupBy('day', 'platform')
            ->get();

        $points = [];
        foreach ($period->buckets() as $bucket) {
            $points[$bucket] = ['date' => $bucket, 'installsIos' => 0, 'installsAndroid' => 0, 'trialsIos' => 0, 'trialsAndroid' => 0];
        }

        foreach ($installs as $row) {
            $key = $row->platform === 'ios' ? 'installsIos' : 'installsAndroid';
            $points[$period->bucketOf((string) $row->day)][$key] += (int) $row->count;
        }

        foreach (Purchases::in($purchases, $period)->where('trial', true) as $row) {
            $points[$period->bucketOf($row['date'])][$row['platform'] === 'ios' ? 'trialsIos' : 'trialsAndroid']++;
        }

        return array_values($points);
    }

    /** @return array{points: list<array{date: string, found: int, empty: int}>, total: int, found: int} */
    private function translationVolume(Period $period): array
    {
        $rows = DB::table('translations')
            ->whereBetween('created_at', $period->range())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total, SUM(CASE WHEN '.Sql::emptyResult().' THEN 1 ELSE 0 END) as empty_count')
            ->groupBy('day')
            ->get();

        $points = [];
        foreach ($period->buckets() as $bucket) {
            $points[$bucket] = ['date' => $bucket, 'found' => 0, 'empty' => 0];
        }

        foreach ($rows as $row) {
            $bucket = $period->bucketOf((string) $row->day);
            $points[$bucket]['empty'] += (int) $row->empty_count;
            $points[$bucket]['found'] += (int) $row->total - (int) $row->empty_count;
        }

        $points = array_values($points);

        return [
            'points' => $points,
            'total' => array_sum(array_map(fn ($p) => $p['found'] + $p['empty'], $points)),
            'found' => array_sum(array_column($points, 'found')),
        ];
    }

    /**
     * New installs per country (from the region in the device language), with
     * the platform split, the previous period and the trials started there
     * (the store's country, from RevenueCat).
     *
     * @return array{rows: list<array<string, mixed>>, unplaced: int, total: int}
     */
    private function countries(Period $period, Period $previous, Collection $purchases): array
    {
        $tally = function (Period $range) {
            $counts = [];
            $unplaced = 0;

            $this->firstOpens($range)
                ->selectRaw('locale, platform, COUNT(DISTINCT install_id) as count')
                ->groupBy('locale', 'platform')
                ->get()
                ->each(function ($row) use (&$counts, &$unplaced) {
                    $code = Country::fromLocale($row->locale);
                    if ($code === null) {
                        $unplaced += (int) $row->count;

                        return;
                    }
                    $platform = $row->platform === 'ios' ? 'ios' : 'android';
                    $counts[$code][$platform] = ($counts[$code][$platform] ?? 0) + (int) $row->count;
                });

            return [$counts, $unplaced];
        };

        [$current, $unplaced] = $tally($period);
        [$before] = $tally($previous);
        $trials = Purchases::in($purchases, $period)->where('trial', true)->countBy('country');

        $rows = collect($current)
            ->map(fn (array $split, string $code) => [
                'code' => $code,
                'installs' => array_sum($split),
                'ios' => $split['ios'] ?? 0,
                'android' => $split['android'] ?? 0,
                'previous' => array_sum($before[$code] ?? []),
                'trials' => (int) ($trials[$code] ?? 0),
            ])
            ->sortByDesc('installs')
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'unplaced' => $unplaced,
            'total' => array_sum(array_column($rows, 'installs')) + $unplaced,
        ];
    }

    /**
     * Who the period's new installs are: platform, the role answered in
     * onboarding, and the phone's language.
     *
     * @return array{platform: list<array{key: string, count: int, previous: int}>, role: list<array{key: string|null, count: int}>, language: list<array{key: string|null, count: int}>}
     */
    private function newUsersBy(Period $period, Period $previous): array
    {
        $byPlatform = fn (Period $range) => $this->firstOpens($range)
            ->selectRaw('platform, COUNT(DISTINCT install_id) as count')
            ->groupBy('platform')
            ->pluck('count', 'platform');

        $now = $byPlatform($period);
        $before = $byPlatform($previous);

        $platform = collect(self::PLATFORMS)
            ->map(fn (string $key) => ['key' => $key, 'count' => (int) ($now[$key] ?? 0), 'previous' => (int) ($before[$key] ?? 0)])
            ->all();

        $installs = $this->firstOpens($period)->select('install_id');

        $roles = DB::table('users')
            ->whereIn('install_id', $installs)
            ->selectRaw('role, COUNT(DISTINCT install_id) as count')
            ->groupBy('role')
            ->get();
        $answered = $roles->whereNotNull('role')->sum('count');
        $total = $this->firstOpens($period)->distinct()->count('install_id');

        $role = $roles->whereNotNull('role')
            ->map(fn ($row) => ['key' => (string) $row->role, 'count' => (int) $row->count])
            ->sortByDesc('count')
            ->values();
        if ($total - $answered > 0) {
            $role->push(['key' => null, 'count' => $total - $answered]);
        }

        $language = [];
        $this->firstOpens($period)
            ->selectRaw('locale, COUNT(DISTINCT install_id) as count')
            ->groupBy('locale')
            ->get()
            ->each(function ($row) use (&$language) {
                $code = strtolower((string) preg_replace('/[-_].*$/', '', (string) $row->locale)) ?: '';
                $language[$code] = ($language[$code] ?? 0) + (int) $row->count;
            });
        arsort($language);

        return [
            'platform' => $platform,
            'role' => $role->all(),
            'language' => collect($language)->map(fn (int $count, string $code) => ['key' => $code === '' ? null : $code, 'count' => $count])->values()->all(),
        ];
    }

    /**
     * From seeing the app in the store to paying, per platform. Store numbers
     * arrive days late (Apple ~2, Google ~8–12), so each platform's funnel
     * ends on the last day its store has reported, and every step is counted
     * over those same days.
     *
     * @return array{platforms: list<array<string, mixed>>, syncedAt: string|null}
     */
    private function funnel(Period $period, Collection $purchases): array
    {
        $platforms = [];

        foreach (self::PLATFORMS as $platform) {
            $storeMetrics = $platform === 'ios' ? ['impressions', 'page_views', 'downloads'] : ['page_views', 'downloads'];
            $last = $this->lastStoreDay($platform, $storeMetrics);
            $window = $last ? $period->endingBy($last) : null;

            $store = $window
                ? DB::table('store_stats')
                    ->where('platform', $platform)
                    ->whereBetween('date', [$window->from->toDateString(), $window->to->toDateString()])
                    ->selectRaw('metric, SUM(value) as total')
                    ->groupBy('metric')
                    ->pluck('total', 'metric')
                : collect();

            $range = $window ?? $period;
            $bought = Purchases::in($purchases, $range)->where('platform', $platform);

            $platforms[] = [
                'platform' => $platform,
                'from' => $range->from->toDateString(),
                'to' => $range->to->toDateString(),
                'hasStoreData' => $window !== null,
                'lastStoreDay' => $last,
                'steps' => [
                    ['key' => 'impressions', 'count' => $platform === 'ios' && $window ? (int) ($store['impressions'] ?? 0) : null],
                    ['key' => 'page_views', 'count' => $window ? (int) ($store['page_views'] ?? 0) : null],
                    ['key' => 'downloads', 'count' => $window ? (int) ($store['downloads'] ?? 0) : null],
                    ['key' => 'first_opens', 'count' => $this->firstOpens($range)->where('platform', $platform)->distinct()->count('install_id')],
                    ['key' => 'trials', 'count' => $bought->where('trial', true)->count()],
                    ['key' => 'paid', 'count' => Purchases::newPayers($bought)],
                ],
            ];
        }

        $synced = DB::table('store_stats')->max('updated_at');

        return [
            'platforms' => $platforms,
            'syncedAt' => $synced ? Carbon::parse($synced)->toIso8601String() : null,
        ];
    }

    /** The latest day for which the store has reported every one of $metrics. */
    private function lastStoreDay(string $platform, array $metrics): ?string
    {
        $days = DB::table('store_stats')
            ->where('platform', $platform)
            ->whereIn('metric', $metrics)
            ->selectRaw('metric, MAX(date) as last')
            ->groupBy('metric')
            ->pluck('last', 'metric');

        if ($days->count() < count($metrics)) {
            return null;
        }

        return substr((string) $days->min(), 0, 10);
    }

    /** @return list<array{lang: string, count: int, emptyRate: float, up: int, down: int}> */
    private function brailleLanguages(Period $period): array
    {
        return Translation::query()
            ->whereBetween('created_at', $period->range())
            ->selectRaw('lang, COUNT(*) as count,
                SUM(CASE WHEN '.Sql::emptyResult().' THEN 1 ELSE 0 END) as empty_count,
                SUM(CASE WHEN rating > 0 THEN 1 ELSE 0 END) as up,
                SUM(CASE WHEN rating < 0 THEN 1 ELSE 0 END) as down')
            ->groupBy('lang')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'lang' => (string) $row->lang,
                'count' => (int) $row->count,
                'emptyRate' => round($row->empty_count / $row->count * 100, 1),
                'up' => (int) $row->up,
                'down' => (int) $row->down,
            ])
            ->all();
    }

    /**
     * Entitlements that grant access right now, from real store purchases:
     * sandbox testers and RevenueCat promotional grants are left out.
     *
     * @return array{paid: int, trial: int}
     */
    private function premiumCounts(): array
    {
        $counts = ['paid' => 0, 'trial' => 0];

        Subscription::query()
            ->where('is_active', true)
            ->where('environment', 'PRODUCTION')
            ->whereIn('store', ['app_store', 'play_store'])
            ->get()
            ->filter(fn (Subscription $subscription) => $subscription->grantsAccess())
            ->each(function (Subscription $subscription) use (&$counts) {
                $counts[$subscription->isTrial() ? 'trial' : 'paid']++;
            });

        return $counts;
    }

    private function events(Period $period): Builder
    {
        return DB::table('analytics_events')->whereBetween('created_at', $period->range());
    }

    /** Installs opened for the first time in the period. */
    private function firstOpens(Period $period): Builder
    {
        return $this->events($period)
            ->where('name', 'app_opened')
            ->where('props->is_first_open', true);
    }

    /** @return array<string, int> Y-m-d => value */
    private function perDay(Builder $query, string $aggregate): array
    {
        return $query
            ->selectRaw("DATE(created_at) as day, {$aggregate} as count")
            ->groupBy('day')
            ->pluck('count', 'day')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Daily values folded into the period's chart buckets, missing days as 0.
     *
     * @param  array<string, int>  $daily
     * @return list<float|int>
     */
    private function bucketed(array $daily, Period $period, string $fold = 'sum'): array
    {
        $buckets = [];
        foreach ($period->dates() as $date) {
            $buckets[$period->bucketOf($date)][] = $daily[$date] ?? 0;
        }

        return array_values(array_map(
            fn (array $values) => $fold === 'avg' ? round(array_sum($values) / count($values), 1) : array_sum($values),
            $buckets,
        ));
    }

    /** @return list<array<string, mixed>> */
    private function recentSubscriptionEvents(): array
    {
        return DB::table('subscription_events')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(function ($row) {
                $event = json_decode((string) $row->payload, true) ?: [];

                return [
                    'id' => (int) $row->id,
                    'type' => $row->type,
                    'periodType' => $event['period_type'] ?? null,
                    'price' => is_numeric($event['price'] ?? null) ? (float) $event['price'] : null,
                    'country' => $event['country_code'] ?? null,
                    'productId' => $row->product_id,
                    'store' => $row->store,
                    'environment' => $row->environment,
                    'userId' => $row->user_id !== null ? (int) $row->user_id : null,
                    'createdAt' => Carbon::parse($row->created_at)->toIso8601String(),
                ];
            })
            ->all();
    }

    /** @return list<array{id: int, name: string|null, email: string|null, provider: string|null, role: string|null, createdAt: string|null}> */
    private function recentUsers(): array
    {
        return User::query()
            ->latest()
            ->latest('id')
            ->limit(6)
            ->get(['id', 'name', 'email', 'auth_provider', 'role', 'created_at'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'provider' => $user->auth_provider,
                'role' => $user->role,
                'createdAt' => $user->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
