<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Real store purchases, read from the RevenueCat webhook events we keep.
 * Sandbox, promotional grants and test events are left out.
 *
 * Each event becomes one plain row:
 *   trial        a free trial started
 *   firstPayment the account paid for the first time: a direct purchase, a
 *                lifetime purchase, or a trial that converted
 *   revenue      USD charged (negative for a refund), before the store's cut
 *   proceeds     what is left after the store's cut, when RevenueCat says
 */
class Purchases
{
    private const STORES = ['APP_STORE' => 'ios', 'PLAY_STORE' => 'android'];

    /** Events whose `price` is money that changed hands. */
    private const CHARGES = ['INITIAL_PURCHASE', 'RENEWAL', 'NON_RENEWING_PURCHASE'];

    /**
     * @return Collection<int, array{date: string, platform: string, country: string, user: string, trial: bool, firstPayment: bool, revenue: float, proceeds: float}>
     */
    public static function between(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return DB::table('subscription_events')
            ->whereBetween('created_at', [$from, $to])
            ->where('environment', 'PRODUCTION')
            ->whereIn('store', array_keys(self::STORES))
            ->orderBy('created_at')
            ->get(['type', 'store', 'rc_user_id', 'payload', 'created_at'])
            ->map(fn (object $row) => self::row($row))
            ->filter()
            ->values();
    }

    private static function row(object $row): ?array
    {
        $event = json_decode((string) $row->payload, true) ?: [];
        $type = (string) $row->type;
        $periodType = strtoupper((string) ($event['period_type'] ?? ''));
        $price = is_numeric($event['price'] ?? null) ? (float) $event['price'] : 0.0;

        $trial = $type === 'INITIAL_PURCHASE' && $periodType === 'TRIAL';
        $firstPayment = match ($type) {
            'INITIAL_PURCHASE' => !$trial && $price > 0,
            'NON_RENEWING_PURCHASE' => $price > 0,
            'RENEWAL' => (bool) ($event['is_trial_conversion'] ?? false),
            default => false,
        };

        // A refund arrives as a cancellation with a negative price.
        $revenue = match (true) {
            in_array($type, self::CHARGES, true) => $price,
            $type === 'CANCELLATION' && $price < 0 => $price,
            default => 0.0,
        };

        if (!$trial && !$firstPayment && $revenue == 0.0) {
            return null;
        }

        $takeHome = is_numeric($event['takehome_percentage'] ?? null) ? (float) $event['takehome_percentage'] : null;

        return [
            'date' => Carbon::parse($row->created_at)->toDateString(),
            'platform' => self::STORES[$row->store],
            'country' => strtoupper((string) ($event['country_code'] ?? '')),
            // The anonymous id a purchase starts under is the stable one.
            'user' => (string) ($event['original_app_user_id'] ?? $row->rc_user_id),
            'trial' => $trial,
            'firstPayment' => $firstPayment,
            'revenue' => $revenue,
            'proceeds' => $takeHome === null ? $revenue : $revenue * $takeHome,
        ];
    }

    /** Rows dated inside the period. */
    public static function in(Collection $rows, Period $period): Collection
    {
        $from = $period->from->toDateString();
        $to = $period->to->toDateString();

        return $rows->filter(fn (array $row) => $row['date'] >= $from && $row['date'] <= $to);
    }

    /** People who paid for the first time, each counted once. */
    public static function newPayers(Collection $rows): int
    {
        return $rows->where('firstPayment', true)->pluck('user')->unique()->count();
    }
}
