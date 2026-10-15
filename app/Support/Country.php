<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * A user's country, read from the region in their device language ("pt-BR"
 * is Brazil) as the app reports it with every analytics event. A language
 * with no region ("en") says nothing about where the phone is.
 *
 * Events are tied to an account through the install ID: the app sends them
 * before it has signed in, so analytics_events.user_id is left empty.
 */
class Country
{
    /** The filter bucket for users with no region in any event. Never a real ISO code. */
    public const UNKNOWN = 'unknown';

    /** SQL for the install ID of the row's user, given the column holding the user's ID. */
    public static function installIdOf(string $userIdColumn): string
    {
        return $userIdColumn === 'users.id'
            ? 'users.install_id'
            : "(SELECT users.install_id FROM users WHERE users.id = {$userIdColumn})";
    }

    public static function fromLocale(?string $locale): ?string
    {
        return preg_match('/^[a-z]{2,3}[-_]([A-Za-z]{2})$/', (string) $locale, $match) ? strtoupper($match[1]) : null;
    }

    /** The device language of the user's latest event, for addSelect(). */
    public static function latestLocale(string $userIdColumn): QueryBuilder
    {
        return DB::table('analytics_events')
            ->select('locale')
            ->whereRaw('analytics_events.install_id = '.self::installIdOf($userIdColumn))
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
        $installId = self::installIdOf($userIdColumn);

        $query->where(function (Builder $q) use ($named, $codes, $installId) {
            if ($named !== []) {
                $q->orWhereExists(fn (QueryBuilder $events) => $events->from('analytics_events')
                    ->whereRaw("analytics_events.install_id = {$installId}")
                    ->where(function (QueryBuilder $locale) use ($named) {
                        foreach ($named as $code) {
                            $locale->orWhere('locale', 'like', "%-{$code}")->orWhere('locale', 'like', "%\\_{$code}");
                        }
                    }));
            }
            if (in_array(self::UNKNOWN, $codes, true)) {
                $q->orWhereNotExists(fn (QueryBuilder $events) => $events->from('analytics_events')
                    ->whereRaw("analytics_events.install_id = {$installId}")
                    ->where(fn (QueryBuilder $l) => $l->where('locale', 'like', '%-__')->orWhere('locale', 'like', '%\\___')));
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
            ->join('users', 'users.install_id', '=', 'analytics_events.install_id')
            ->when($userIds, fn (QueryBuilder $q) => $q->whereIn('users.id', $userIds))
            ->selectRaw('analytics_events.locale as locale, COUNT(DISTINCT users.id) as count')
            ->groupBy('analytics_events.locale')
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
