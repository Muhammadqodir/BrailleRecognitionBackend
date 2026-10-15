<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * A user's country, read from the region in their device language ("pt-BR"
 * is Brazil) as the app reports it with every analytics event. A language
 * with no region ("en") says nothing about where the phone is.
 */
class Country
{
    /** The filter bucket for users with no region in any event. Never a real ISO code. */
    public const UNKNOWN = 'unknown';

    public static function fromLocale(?string $locale): ?string
    {
        return preg_match('/^[a-z]{2,3}[-_]([A-Za-z]{2})$/', (string) $locale, $match) ? strtoupper($match[1]) : null;
    }

    /** The device language of the user's latest event, for addSelect(). */
    public static function latestLocale(string $userIdColumn): QueryBuilder
    {
        return DB::table('analytics_events')
            ->select('locale')
            ->whereColumn('analytics_events.user_id', $userIdColumn)
            ->whereNotNull('locale')
            ->orderByDesc('id')
            ->limit(1);
    }

    /**
     * Keeps rows whose user ever reported one of $codes.
     *
     * @param  list<string>  $codes
     */
    public static function filter(Builder $query, string $userIdColumn, array $codes): void
    {
        $named = array_values(array_filter($codes, fn (string $code) => preg_match('/^[A-Z]{2}$/', $code)));
        $withRegion = fn (QueryBuilder $q) => $q->whereColumn('analytics_events.user_id', $userIdColumn)
            ->where(fn (QueryBuilder $q) => $q->where('locale', 'like', '%-__')->orWhere('locale', 'like', '%\___'));

        $query->where(function (Builder $q) use ($named, $codes, $userIdColumn, $withRegion) {
            if ($named !== []) {
                $q->orWhereExists(fn (QueryBuilder $events) => $events->from('analytics_events')
                    ->whereColumn('analytics_events.user_id', $userIdColumn)
                    ->where(function (QueryBuilder $locale) use ($named) {
                        foreach ($named as $code) {
                            $locale->orWhere('locale', 'like', "%-{$code}")->orWhere('locale', 'like', "%\\_{$code}");
                        }
                    }));
            }
            if (in_array(self::UNKNOWN, $codes, true)) {
                $q->orWhereNotExists(fn (QueryBuilder $events) => $withRegion($events->from('analytics_events')));
            }
        });
    }

    /**
     * Users per country, biggest first. A user who changed their phone's
     * language counts in both countries.
     *
     * @param  QueryBuilder|null  $userIds  limits the count to these users
     * @return array<string, int>
     */
    public static function counts(?QueryBuilder $userIds = null): array
    {
        $counts = [];

        DB::table('analytics_events')
            ->whereNotNull('user_id')
            ->when($userIds, fn (QueryBuilder $q) => $q->whereIn('user_id', $userIds))
            ->selectRaw('locale, COUNT(DISTINCT user_id) as count')
            ->groupBy('locale')
            ->get()
            ->each(function ($row) use (&$counts) {
                if ($code = self::fromLocale($row->locale)) {
                    $counts[$code] = ($counts[$code] ?? 0) + (int) $row->count;
                }
            });

        arsort($counts);

        return $counts;
    }
}
