import { Activity, ArrowDownRight, ArrowUpRight, BadgeDollarSign, CreditCard, Info, Minus, ScanText, Sparkles, UserPlus, type LucideIcon } from 'lucide-react';
import { useId } from 'react';
import { Area, AreaChart, Bar, BarChart, Tooltip as ChartTip, XAxis } from 'recharts';

import { formatDay, formatMoney, formatNumber, trend, type Trend } from '@/components/dashboard/format';
import type { PeriodInfo } from '@/components/dashboard/period-picker';
import { Card } from '@/components/ui/card';
import { ChartContainer, type ChartConfig } from '@/components/ui/chart';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

export interface Kpi {
    key: 'active' | 'newUsers' | 'paid' | 'trials' | 'translations' | 'earnings';
    value: number;
    previous: number;
    today: number;
    series: number[];
    now?: number;
    proceeds?: number;
}

const KPI_META: Record<Kpi['key'], { label: string; icon: LucideIcon; color: string; hint: string; money?: boolean }> = {
    active: {
        label: 'Active users',
        icon: Activity,
        color: 'var(--viz-blue)',
        hint: 'Installs that opened the app at least once in the period. The chart shows each day.',
    },
    newUsers: {
        label: 'New users',
        icon: UserPlus,
        color: 'var(--viz-aqua)',
        hint: 'Installs opened for the first time in the period.',
    },
    paid: {
        label: 'Paid users',
        icon: CreditCard,
        color: 'var(--viz-magenta)',
        hint: 'People who paid for the first time: a purchase, a lifetime buy, or a trial that turned into a subscription. Real store purchases only.',
    },
    trials: {
        label: 'Trial users',
        icon: Sparkles,
        color: 'var(--viz-orange)',
        hint: 'Free trials started in the period. Real store purchases only.',
    },
    translations: {
        label: 'Translations',
        icon: ScanText,
        color: 'var(--viz-violet)',
        hint: 'Photos read, on the phone or on the OCR server.',
    },
    earnings: {
        label: 'Earnings',
        icon: BadgeDollarSign,
        color: 'var(--viz-green)',
        hint: 'What customers paid in USD, refunds taken off, before the store keeps its share. From the RevenueCat webhook.',
        money: true,
    },
};

const SPARSE = new Set<Kpi['key']>(['paid', 'trials', 'earnings']);

const TREND_STYLE: Record<Trend['direction'], { icon: LucideIcon | null; className: string }> = {
    up: { icon: ArrowUpRight, className: 'text-trend-up bg-trend-up/10' },
    down: { icon: ArrowDownRight, className: 'text-trend-down bg-trend-down/10' },
    flat: { icon: Minus, className: 'text-muted-foreground bg-muted' },
    new: { icon: null, className: 'text-muted-foreground bg-muted' },
};

function previousLabel(period: PeriodInfo): string {
    if (period.key === 'today') {
        return 'yesterday';
    }
    if (period.key === 'custom') {
        return `the ${period.days} days before`;
    }

    return `previous ${period.days === 365 ? '12 months' : `${period.days} days`}`;
}

export function KpiCard({ kpi, period, buckets }: { kpi: Kpi; period: PeriodInfo; buckets: string[] }) {
    const meta = KPI_META[kpi.key];
    const format = (value: number) => (meta.money ? formatMoney(value) : formatNumber(value));
    const change = trend(kpi.value, kpi.previous);
    const TrendIcon = TREND_STYLE[change.direction].icon;
    const gradientId = useId().replace(/:/g, '');
    const data = kpi.series.map((value, index) => ({ date: buckets[index], value }));
    const config = { value: { label: meta.label, color: meta.color } } satisfies ChartConfig;
    const showToday = period.includesToday && period.key !== 'today';
    const Icon = meta.icon;
    const tooltip = ({ active, payload }: { active?: boolean; payload?: readonly { value?: unknown; payload?: { date?: string } }[] }) =>
        active && payload?.length ? (
            <div className="rounded-md border bg-popover px-2 py-1 text-xs shadow-sm">
                <span className="text-muted-foreground">
                    {period.bucket === 'week' ? 'Week of ' : ''}
                    {formatDay(String(payload[0].payload?.date))}
                </span>{' '}
                <span className="font-medium tabular-nums">{format(Number(payload[0].value))}</span>
            </div>
        ) : null;

    const extra =
        kpi.key === 'paid' && kpi.now !== undefined
            ? `${formatNumber(kpi.now)} paying now`
            : kpi.key === 'trials' && kpi.now !== undefined
              ? `${formatNumber(kpi.now)} in trial now`
              : kpi.key === 'earnings' && kpi.proceeds !== undefined
                ? `≈ ${formatMoney(kpi.proceeds)} after store fees`
                : null;

    return (
        <Card className="gap-0 py-0">
            <div className="flex flex-col gap-2.5 p-4 pb-2">
                <div className="flex min-w-0 items-center gap-2">
                    <span
                        className="flex size-7 shrink-0 items-center justify-center rounded-lg"
                        style={{ background: `color-mix(in oklch, ${meta.color} 14%, transparent)`, color: meta.color }}
                    >
                        <Icon className="size-4" />
                    </span>
                    <span className="truncate text-sm font-medium text-muted-foreground">{meta.label}</span>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Info className="size-3.5 shrink-0 text-muted-foreground/60" aria-label={meta.hint} />
                        </TooltipTrigger>
                        <TooltipContent className="max-w-64">{meta.hint}</TooltipContent>
                    </Tooltip>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
                    <span className="text-[1.75rem] leading-none font-semibold tracking-tight">{format(kpi.value)}</span>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span className={cn('inline-flex shrink-0 items-center gap-0.5 rounded-md px-1.5 py-0.5 text-xs font-medium tabular-nums', TREND_STYLE[change.direction].className)}>
                                {TrendIcon && <TrendIcon className="size-3.5" />}
                                {change.text}
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>
                            {format(kpi.value)} vs {format(kpi.previous)} {previousLabel(period)}
                        </TooltipContent>
                    </Tooltip>
                </div>

                <div className="flex min-w-0 flex-col gap-0.5 text-xs text-muted-foreground">
                    {showToday && (
                        <span className="whitespace-nowrap">
                            <span className={cn('font-semibold', kpi.today !== 0 ? 'text-foreground' : 'text-muted-foreground')}>
                                {kpi.today > 0 ? '+' : ''}
                                {format(kpi.today)}
                            </span>{' '}
                            today
                        </span>
                    )}
                    <span className="truncate">{extra ?? `${format(kpi.previous)} ${previousLabel(period)}`}</span>
                </div>
            </div>

            <ChartContainer config={config} className="aspect-auto h-12 w-full">
                {SPARSE.has(kpi.key) ? (
                    // A few events on scattered days: bars, so a lone sale is not drawn as a wave.
                    <BarChart data={data} margin={{ top: 6, right: 12, bottom: 2, left: 12 }} barCategoryGap="20%">
                        <XAxis dataKey="date" hide />
                        <ChartTip cursor={{ fill: 'var(--muted)', opacity: 0.6 }} content={tooltip} />
                        <Bar dataKey="value" fill="var(--color-value)" radius={[2, 2, 0, 0]} minPointSize={0} isAnimationActive={false} />
                    </BarChart>
                ) : (
                    <AreaChart data={data} margin={{ top: 4, right: 0, bottom: 0, left: 0 }}>
                        <defs>
                            <linearGradient id={`spark-${gradientId}`} x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor="var(--color-value)" stopOpacity={0.28} />
                                <stop offset="100%" stopColor="var(--color-value)" stopOpacity={0} />
                            </linearGradient>
                        </defs>
                        <XAxis dataKey="date" hide />
                        <ChartTip cursor={{ stroke: 'var(--border)' }} content={tooltip} />
                        <Area
                            dataKey="value"
                            type="monotone"
                            stroke="var(--color-value)"
                            strokeWidth={1.5}
                            fill={`url(#spark-${gradientId})`}
                            isAnimationActive={false}
                            activeDot={{ r: 3, strokeWidth: 0 }}
                        />
                    </AreaChart>
                )}
            </ChartContainer>
        </Card>
    );
}
