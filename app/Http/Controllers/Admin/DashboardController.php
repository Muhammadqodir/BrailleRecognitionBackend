<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Translation;
use App\Models\User;
use App\Support\Country;
use App\Support\Sql;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The funnel, in the order a new install meets it. Each step counts the
     * installs from the cohort that ever sent that event, so a step can be
     * reached without the one before it (a user can skip onboarding).
     */
    private const FUNNEL = [
        'app_opened' => 'Opened the app',
        'onboarding_completed' => 'Finished onboarding',
        'scan_completed' => 'Read a photo',
        'paywall_viewed' => 'Saw the paywall',
        'purchase_started' => 'Started a purchase',
        'trial_started' => 'Started a trial',
    ];

    public function index(): Response
    {
        return Inertia::render('dashboard', [
            'kpis' => $this->kpis(),
            'userGrowth' => $this->dailySeries(User::query(), 'created_at', 30),
            'activeInstalls' => $this->dailySeries(
                DB::table('analytics_events'),
                'created_at',
                30,
                'COUNT(DISTINCT install_id)',
            ),
            'platforms' => $this->platformBreakdown(),
            'installsByCountry' => $this->installsByCountry(),
            'translationsDaily' => $this->translationsDaily(),
            'topLanguages' => $this->topLanguages(),
            'funnel' => $this->funnel(),
            'subscriptionEvents' => $this->recentSubscriptionEvents(),
            'recentUsers' => $this->recentUsers(),
        ]);
    }

    /** @return array<string, array{value: int|float|null, delta: float|null}> */
    private function kpis(): array
    {
        $now = now();
        $weekAgo = $now->copy()->subDays(7);
        $twoWeeksAgo = $now->copy()->subDays(14);

        $newThisWeek = User::where('created_at', '>=', $weekAgo)->count();
        $newPrevWeek = User::whereBetween('created_at', [$twoWeeksAgo, $weekAgo])->count();

        $activeThisWeek = User::where('last_seen_at', '>=', $weekAgo)->count();
        $activePrevWeek = User::whereBetween('last_seen_at', [$twoWeeksAgo, $weekAgo])->count();

        $translationsThisWeek = Translation::where('created_at', '>=', $weekAgo)->count();
        $translationsPrevWeek = Translation::whereBetween('created_at', [$twoWeeksAgo, $weekAgo])->count();

        $readRate = $this->readRate(Translation::where('created_at', '>=', $weekAgo));
        $prevReadRate = $this->readRate(Translation::whereBetween('created_at', [$twoWeeksAgo, $weekAgo]));

        $premium = $this->premiumCounts();

        $rated = Translation::where('created_at', '>=', $now->copy()->subDays(30))->whereNotNull('rating');

        return [
            'totalUsers' => ['value' => User::count(), 'delta' => null],
            'signedInUsers' => ['value' => User::where('auth_provider', '<>', 'anonymous')->count(), 'delta' => null],
            'activeToday' => ['value' => User::where('last_seen_at', '>=', $now->copy()->startOfDay())->count(), 'delta' => null],
            'activeThisWeek' => ['value' => $activeThisWeek, 'delta' => $this->percentChange($activeThisWeek, $activePrevWeek)],
            'newThisWeek' => ['value' => $newThisWeek, 'delta' => $this->percentChange($newThisWeek, $newPrevWeek)],
            'translationsThisWeek' => ['value' => $translationsThisWeek, 'delta' => $this->percentChange($translationsThisWeek, $translationsPrevWeek)],
            'totalTranslations' => ['value' => Translation::count(), 'delta' => null],
            'paidSubscribers' => ['value' => $premium['paid'], 'delta' => null],
            'trials' => ['value' => $premium['trial'], 'delta' => null],
            'readRate' => [
                'value' => $readRate,
                'delta' => $readRate !== null && $prevReadRate !== null ? round($readRate - $prevReadRate, 1) : null,
            ],
            'thumbsUp' => ['value' => (clone $rated)->where('rating', '>', 0)->count(), 'delta' => null],
            'thumbsDown' => ['value' => (clone $rated)->where('rating', '<', 0)->count(), 'delta' => null],
        ];
    }

    /** Share of translations that found text, in percent; null when there were none. */
    private function readRate(EloquentBuilder $query): ?float
    {
        $total = (clone $query)->count();

        if ($total === 0) {
            return null;
        }

        $read = (clone $query)->whereRaw('NOT '.Sql::emptyResult())->count();

        return round($read / $total * 100, 1);
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

    /** Relative change in percent, or null when there is no prior-period baseline to compare against. */
    private function percentChange(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    /**
     * Daily count series for the last $days days, gap-filled with 0 so the chart never
     * has to special-case missing dates.
     *
     * @return list<array{date: string, count: int}>
     */
    private function dailySeries(EloquentBuilder|Builder $query, string $column, int $days, string $aggregate = 'COUNT(*)'): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $counts = $query
            ->where($column, '>=', $since)
            ->selectRaw("DATE({$column}) as date, {$aggregate} as count")
            ->groupBy('date')
            ->pluck('count', 'date');

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($counts, $days) {
                $date = now()->subDays($days - 1 - $offset)->format('Y-m-d');

                return ['date' => $date, 'count' => (int) ($counts->get($date) ?? 0)];
            })
            ->all();
    }

    /** @return list<array{platform: string, count: int}> */
    private function platformBreakdown(): array
    {
        return DB::table('analytics_events')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw("COALESCE(NULLIF(platform, ''), 'unknown') as platform, COUNT(DISTINCT install_id) as count")
            ->groupBy('platform')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['platform' => $row->platform, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * Installs per country, read from the region in the device locale ("pt-BR"
     * is Brazil). A locale with no region ("en") says nothing about where the
     * phone is, so those installs are reported apart instead of guessed.
     *
     * @return array{countries: list<array{code: string, count: int}>, unplacedInstalls: int}
     */
    private function installsByCountry(): array
    {
        $counts = [];
        $unplaced = 0;

        DB::table('analytics_events')
            ->selectRaw('locale, COUNT(DISTINCT install_id) as count')
            ->groupBy('locale')
            ->get()
            ->each(function ($row) use (&$counts, &$unplaced) {
                if ($code = Country::fromLocale($row->locale)) {
                    $counts[$code] = ($counts[$code] ?? 0) + (int) $row->count;
                } else {
                    $unplaced += (int) $row->count;
                }
            });

        arsort($counts);

        return [
            'countries' => collect($counts)->map(fn (int $count, string $code) => ['code' => $code, 'count' => $count])->values()->all(),
            'unplacedInstalls' => $unplaced,
        ];
    }

    /** @return list<array{date: string, device: int, server: int, empty: int}> */
    private function translationsDaily(): array
    {
        $days = 14;
        $since = now()->subDays($days - 1)->startOfDay();
        $engine = Sql::engine();

        $rows = Translation::query()
            ->where('created_at', '>=', $since)
            ->selectRaw("DATE(created_at) as date,
                SUM(CASE WHEN {$engine} = 'device' THEN 1 ELSE 0 END) as device,
                SUM(CASE WHEN {$engine} = 'server' THEN 1 ELSE 0 END) as server")
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($rows, $days) {
                $date = now()->subDays($days - 1 - $offset)->format('Y-m-d');
                $row = $rows->get($date);

                return [
                    'date' => $date,
                    'device' => (int) ($row->device ?? 0),
                    'server' => (int) ($row->server ?? 0),
                ];
            })
            ->all();
    }

    /** @return list<array{lang: string, count: int, emptyRate: float, up: int, down: int}> */
    private function topLanguages(): array
    {
        return Translation::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('lang, COUNT(*) as count,
                SUM(CASE WHEN '.Sql::emptyResult().' THEN 1 ELSE 0 END) as empty_count,
                SUM(CASE WHEN rating > 0 THEN 1 ELSE 0 END) as up,
                SUM(CASE WHEN rating < 0 THEN 1 ELSE 0 END) as down')
            ->groupBy('lang')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'lang' => $row->lang,
                'count' => (int) $row->count,
                'emptyRate' => round($row->empty_count / $row->count * 100, 1),
                'up' => (int) $row->up,
                'down' => (int) $row->down,
            ])
            ->all();
    }

    /**
     * New installs from the last 30 days and how far each of them got.
     *
     * @return array{cohort: int, steps: list<array{key: string, label: string, count: int}>}
     */
    private function funnel(): array
    {
        $cohort = DB::table('analytics_events')
            ->select('install_id')
            ->where('name', 'app_opened')
            ->where('props->is_first_open', true)
            ->where('created_at', '>=', now()->subDays(30));

        $reached = DB::table('analytics_events')
            ->whereIn('install_id', $cohort)
            ->whereIn('name', array_keys(self::FUNNEL))
            ->selectRaw('name, COUNT(DISTINCT install_id) as count')
            ->groupBy('name')
            ->pluck('count', 'name');

        $size = (clone $cohort)->distinct()->count('install_id');

        return [
            'cohort' => $size,
            'steps' => collect(self::FUNNEL)
                ->map(fn (string $label, string $key) => [
                    'key' => $key,
                    'label' => $label,
                    'count' => $key === 'app_opened' ? $size : (int) ($reached->get($key) ?? 0),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return list<array{id: int, type: string, productId: string|null, store: string|null, environment: string|null, userId: int|null, createdAt: string}> */
    private function recentSubscriptionEvents(): array
    {
        return DB::table('subscription_events')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'type' => $row->type,
                'productId' => $row->product_id,
                'store' => $row->store,
                'environment' => $row->environment,
                'userId' => $row->user_id !== null ? (int) $row->user_id : null,
                'createdAt' => Carbon::parse($row->created_at)->toIso8601String(),
            ])
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
