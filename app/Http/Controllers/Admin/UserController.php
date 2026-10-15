<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Translation;
use App\Models\User;
use App\Support\Country;
use App\Support\Sql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /** @var list<string> */
    private const SORTABLE = ['created_at', 'last_seen_at', 'translations_count'];

    /** The plan filter's buckets, matching what premiumCounts() on the dashboard counts. */
    private const PLANS = ['paid', 'trial', 'free'];

    public function index(Request $request): Response
    {
        $sort = in_array($request->string('sort')->value(), self::SORTABLE, true)
            ? $request->string('sort')->value()
            : 'created_at';
        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 30, 50], true)
            ? (int) $request->input('per_page')
            : 20;

        $providers = array_values((array) $request->input('provider', []));
        $plans = array_values(array_intersect((array) $request->input('plan', []), self::PLANS));
        $roles = array_values((array) $request->input('role', []));
        $countries = array_values((array) $request->input('country', []));

        $users = User::query()
            ->with('subscription')
            ->withCount('translations')
            ->addSelect([
                // The platform of the install that last reported in for this account.
                'platform' => DB::table('analytics_events')
                    ->select('platform')
                    ->whereColumn('analytics_events.install_id', 'users.install_id')
                    ->orderByDesc('id')
                    ->limit(1),
                'locale' => Country::latestLocale('users.id'),
            ])
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->where(function (Builder $q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('rc_user_id', $search);
                    if (ctype_digit($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            })
            ->when($providers !== [], fn (Builder $query) => $this->applyValueFilter($query, 'auth_provider', $providers))
            ->when($roles !== [], fn (Builder $query) => $this->applyValueFilter($query, 'role', $roles))
            ->when($plans !== [], fn (Builder $query) => $this->applyPlanFilter($query, $plans))
            ->when($countries !== [], fn (Builder $query) => Country::filter($query, 'users.id', $countries))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $users->through(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'rc_user_id' => $user->rc_user_id,
            'provider' => $user->auth_provider,
            'role' => $user->role,
            'platform' => $user->platform,
            'country' => Country::fromLocale($user->locale),
            'plan' => $this->plan($user->subscription),
            'translations_count' => $user->translations_count,
            'last_seen_at' => $user->last_seen_at,
            'created_at' => $user->created_at,
        ]);

        return Inertia::render('users', [
            'users' => $users,
            'providerCounts' => $this->counts('auth_provider'),
            'roleCounts' => $this->counts('role', 12),
            'planCounts' => $this->planCounts(),
            'countryCounts' => Country::counts(),
            'filters' => [
                'search' => $request->string('search')->value() ?: null,
                'provider' => $providers,
                'plan' => $plans,
                'role' => $roles,
                'country' => $countries,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('subscription')->loadCount('translations');

        // Events carry the install, not the account (see App\Support\Country).
        $events = DB::table('analytics_events')->where('install_id', $user->install_id ?? '');
        $lastEvent = (clone $events)->orderByDesc('id')->first();
        $engine = Sql::engine();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'provider' => $user->auth_provider,
            'role' => $user->role,
            'use_case' => $user->use_case,
            'rc_user_id' => $user->rc_user_id,
            'legacy_id' => $user->legacy_id,
            'platform' => $lastEvent?->platform,
            'app_version' => $lastEvent?->app_version,
            'locale' => $lastEvent?->locale,
            'country' => Country::fromLocale($lastEvent?->locale),
            'events_count' => (clone $events)->count(),
            'last_seen_at' => $user->last_seen_at,
            'created_at' => $user->created_at,
            'plan' => $this->plan($user->subscription),
            'subscription' => $user->subscription?->only([
                'product_id', 'store', 'period_type', 'status', 'is_active', 'will_renew',
                'environment', 'purchased_at', 'expires_at',
            ]),
            'translations_count' => $user->translations_count,
            'translations' => $user->translations()
                ->select(['id', 'lang', 'result', 'result_marked', 'rating', 'created_at'])
                ->selectRaw("{$engine} as engine")
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (Translation $translation) => [
                    'id' => $translation->id,
                    'lang' => $translation->lang,
                    'text' => mb_strimwidth(trim((string) $translation->result), 0, 140, '…'),
                    'image_url' => $translation->markedImageUrl(),
                    'engine' => $translation->engine,
                    'rating' => $translation->rating,
                    'created_at' => $translation->created_at,
                ]),
        ]]);
    }

    /** paid, trial or free, the same way the app decides whether to show the paywall. */
    private function plan(?Subscription $subscription): string
    {
        if ($subscription === null || ! $subscription->grantsAccess()) {
            return 'free';
        }

        return $subscription->isTrial() ? 'trial' : 'paid';
    }

    /** @param list<string> $plans */
    private function applyPlanFilter(Builder $query, array $plans): void
    {
        $grantsAccess = fn (Builder $q) => $q->where('entitlement', 'Premium')
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()->subHour()));

        $query->where(function (Builder $q) use ($plans, $grantsAccess) {
            if (in_array('paid', $plans, true)) {
                $q->orWhereHas('subscription', fn (Builder $s) => $grantsAccess($s)->whereRaw("UPPER(COALESCE(period_type, '')) <> 'TRIAL'"));
            }
            if (in_array('trial', $plans, true)) {
                $q->orWhereHas('subscription', fn (Builder $s) => $grantsAccess($s)->whereRaw("UPPER(period_type) = 'TRIAL'"));
            }
            if (in_array('free', $plans, true)) {
                $q->orWhereDoesntHave('subscription', fn (Builder $s) => $grantsAccess($s));
            }
        });
    }

    /**
     * Matches the buckets counts() reports, where an empty column is "unknown".
     *
     * @param  list<string>  $values
     */
    private function applyValueFilter(Builder $query, string $column, array $values): void
    {
        $query->where(function (Builder $q) use ($column, $values) {
            if (in_array('unknown', $values, true)) {
                $q->orWhereNull($column)->orWhere($column, '');
            }
            $named = array_values(array_diff($values, ['unknown']));
            if ($named !== []) {
                $q->orWhereIn($column, $named);
            }
        });
    }

    /** @return array<string, int> */
    private function counts(string $column, int $limit = 20): array
    {
        return User::query()
            ->selectRaw("COALESCE(NULLIF({$column}, ''), 'unknown') as value, COUNT(*) as count")
            ->groupBy('value')
            ->orderByDesc('count')
            ->limit($limit)
            ->pluck('count', 'value')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /** @return array<string, int> */
    private function planCounts(): array
    {
        $counts = ['paid' => 0, 'trial' => 0];

        Subscription::query()
            ->where('entitlement', 'Premium')
            ->where('is_active', true)
            ->get()
            ->each(function (Subscription $subscription) use (&$counts) {
                $plan = $this->plan($subscription);
                if ($plan !== 'free') {
                    $counts[$plan]++;
                }
            });

        return [...$counts, 'free' => User::count() - $counts['paid'] - $counts['trial']];
    }
}
