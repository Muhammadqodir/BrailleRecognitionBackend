<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Support\Country;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    /** @var list<string> */
    private const SORTABLE = ['purchased_at', 'expires_at', 'updated_at'];

    /** @var list<string> */
    private const FILTERS = ['status', 'store', 'period_type', 'environment', 'product_id'];

    public function index(Request $request): Response
    {
        $sort = in_array($request->string('sort')->value(), self::SORTABLE, true)
            ? $request->string('sort')->value()
            : 'updated_at';
        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 30, 50], true)
            ? (int) $request->input('per_page')
            : 20;

        $filters = collect(self::FILTERS)
            ->mapWithKeys(fn (string $column) => [$column => array_values((array) $request->input($column, []))])
            ->all();

        $countries = array_values((array) $request->input('country', []));

        $query = $this->purchases()
            ->with('user:id,name,email,auth_provider')
            ->addSelect(['locale' => Country::latestLocale('subscriptions.user_id')])
            ->when($countries !== [], fn (Builder $q) => Country::filter($q, 'subscriptions.user_id', $countries))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->where(fn (Builder $q) => $q
                    ->where('rc_user_id', $search)
                    ->orWhere('original_transaction_id', $search)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('email', 'like', "%{$search}%")));
            });

        foreach ($filters as $column => $values) {
            $query->when($values !== [], fn (Builder $q) => $q->whereIn($column, $values));
        }

        $subscriptions = $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $subscriptions->through(fn (Subscription $subscription) => [
            'id' => $subscription->id,
            'user' => $subscription->user ? [
                'id' => $subscription->user->id,
                'name' => $subscription->user->name,
                'email' => $subscription->user->email,
                'provider' => $subscription->user->auth_provider,
            ] : null,
            'country' => Country::fromLocale($subscription->locale),
            'product_id' => $subscription->product_id,
            'store' => $subscription->store,
            'period_type' => $subscription->period_type,
            'status' => $subscription->status,
            'grants_access' => $subscription->grantsAccess(),
            'will_renew' => $subscription->will_renew,
            'environment' => $subscription->environment,
            'purchased_at' => $subscription->purchased_at,
            'expires_at' => $subscription->expires_at,
        ]);

        return Inertia::render('subscriptions', [
            'subscriptions' => $subscriptions,
            'facetCounts' => collect(self::FILTERS)
                ->mapWithKeys(fn (string $column) => [$column => $this->purchases()
                    ->selectRaw("{$column} as value, COUNT(*) as count")
                    ->whereNotNull($column)
                    ->groupBy('value')
                    ->orderByDesc('count')
                    ->pluck('count', 'value')
                    ->map(fn ($count) => (int) $count)])
                ->all(),
            'countryCounts' => Country::counts($this->purchases()->toBase()->select('user_id')),
            'filters' => [
                'search' => $request->string('search')->value() ?: null,
                ...$filters,
                'country' => $countries,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Rows for people who actually bought or started a trial. Every account
     * that ever asked for its status gets a row too, marked "inactive" with
     * no store, and those are noise here.
     */
    private function purchases(): Builder
    {
        return Subscription::query()->whereNotNull('store');
    }
}
