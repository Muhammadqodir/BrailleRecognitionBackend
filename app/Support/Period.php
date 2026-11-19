<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The date range the dashboard reports on, whole days in the app's timezone,
 * and the equally long range right before it to compare with.
 */
final class Period
{
    /** Preset key => length in days. */
    public const PRESETS = ['today' => 1, '7d' => 7, '30d' => 30, '90d' => 90, '12m' => 365];

    public const DEFAULT = '30d';

    /** Longer ranges are charted by week, or the bars get too thin to read. */
    private const DAILY_UP_TO = 92;

    private const MAX_DAYS = 731;

    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $key,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $today = CarbonImmutable::today();
        $from = self::date($request->query('from'));
        $to = self::date($request->query('to'));

        if ($from !== null && $to !== null) {
            [$from, $to] = $from->greaterThan($to) ? [$to, $from] : [$from, $to];
            $to = $to->min($today);
            $from = $from->max($to->subDays(self::MAX_DAYS - 1))->min($to);

            return new self($from, $to->endOfDay(), 'custom');
        }

        $key = array_key_exists((string) $request->query('range'), self::PRESETS) ? (string) $request->query('range') : self::DEFAULT;

        return self::preset($key);
    }

    public static function preset(string $key): self
    {
        $today = CarbonImmutable::today();

        return new self($today->subDays(self::PRESETS[$key] - 1), $today->endOfDay(), $key);
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to->startOfDay()) + 1;
    }

    /** The same number of days, ending the day before this one starts. */
    public function previous(): self
    {
        $to = $this->from->subDay();

        return new self($to->subDays($this->days() - 1)->startOfDay(), $to->endOfDay(), 'previous');
    }

    public function bucket(): string
    {
        return $this->days() <= self::DAILY_UP_TO ? 'day' : 'week';
    }

    public function includesToday(): bool
    {
        return $this->to->isSameDay(CarbonImmutable::today());
    }

    /** @return list<string> Y-m-d of every day in the range */
    public function dates(): array
    {
        $dates = [];
        for ($day = $this->from; $day->lessThanOrEqualTo($this->to); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    /** The first day of the chart bucket a date falls in (a Monday, for weeks). */
    public function bucketOf(string $date): string
    {
        if ($this->bucket() === 'day') {
            return $date;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date)->startOfWeek();

        return $day->max($this->from)->toDateString();
    }

    /** @return list<string> the start of every chart bucket, in order */
    public function buckets(): array
    {
        return array_values(array_unique(array_map(fn (string $date) => $this->bucketOf($date), $this->dates())));
    }

    /** A copy that ends no later than $last (e.g. the last day a store has reported); null when nothing is left. */
    public function endingBy(string $last): ?self
    {
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $last)->endOfDay()->min($this->to);

        return $end->lessThan($this->from) ? null : new self($this->from, $end, $this->key);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} for whereBetween() */
    public function range(): array
    {
        return [$this->from, $this->to];
    }

    public function toArray(): array
    {
        $previous = $this->previous();

        return [
            'key' => $this->key,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'days' => $this->days(),
            'bucket' => $this->bucket(),
            'previousFrom' => $previous->from->toDateString(),
            'previousTo' => $previous->to->toDateString(),
            'includesToday' => $this->includesToday(),
            'today' => CarbonImmutable::today()->toDateString(),
            'timezone' => config('app.timezone'),
        ];
    }
}
