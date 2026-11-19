import { Apple, Info, Play } from 'lucide-react';

import { formatDay, formatNumber, formatPercent, formatRange, share } from '@/components/dashboard/format';
import type { PeriodInfo } from '@/components/dashboard/period-picker';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type StepKey = 'impressions' | 'page_views' | 'downloads' | 'first_opens' | 'trials' | 'paid';

export interface FunnelPlatform {
    platform: 'ios' | 'android';
    from: string;
    to: string;
    hasStoreData: boolean;
    lastStoreDay: string | null;
    steps: { key: StepKey; count: number | null }[];
}

export interface FunnelData {
    platforms: FunnelPlatform[];
    syncedAt: string | null;
}

const STEP_LABEL: Record<StepKey, string> = {
    impressions: 'Saw it in the store',
    page_views: 'Opened the app page',
    downloads: 'Downloaded',
    first_opens: 'Opened the app',
    trials: 'Started a trial',
    paid: 'Paid',
};

const STEP_HINT: Record<StepKey, Record<FunnelPlatform['platform'], string>> = {
    impressions: {
        ios: 'Times the app was shown in App Store search, browse or elsewhere (App Store Connect).',
        android: 'Google Play does not export impressions.',
    },
    page_views: {
        ios: 'Views of the product page, in the App Store or as a sheet inside another app.',
        android: 'Store listing visitors (Play Console).',
    },
    downloads: { ios: 'First-time downloads; re-downloads left out.', android: 'Daily user installs (Play Console).' },
    first_opens: { ios: 'Installs that opened the app for the first time (our analytics).', android: 'Installs that opened the app for the first time (our analytics).' },
    trials: { ios: 'Free trials started (RevenueCat).', android: 'Free trials started (RevenueCat).' },
    paid: {
        ios: 'First payments: direct purchases, lifetime buys and trials that converted (RevenueCat).',
        android: 'First payments: direct purchases, lifetime buys and trials that converted (RevenueCat).',
    },
};

const STORE_STEPS: StepKey[] = ['impressions', 'page_views', 'downloads'];

const PLATFORM = {
    ios: { name: 'App Store', icon: Apple, bar: 'bg-viz-ios', tint: 'var(--viz-ios)' },
    android: { name: 'Google Play', icon: Play, bar: 'bg-viz-android', tint: 'var(--viz-android)' },
} as const;

function PlatformFunnel({ data, period }: { data: FunnelPlatform; period: PeriodInfo }) {
    const meta = PLATFORM[data.platform];
    const Icon = meta.icon;
    const known = data.steps.filter((step) => step.count !== null);
    const top = Math.max(1, ...known.map((step) => step.count ?? 0));
    const first = known[0];
    const paid = data.steps.find((step) => step.key === 'paid')?.count ?? 0;
    const cut = data.to !== period.to;

    return (
        <div className="flex min-w-0 flex-col gap-4 rounded-xl border border-border/70 p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <span
                        className="flex size-7 items-center justify-center rounded-lg"
                        style={{ background: `color-mix(in oklch, ${meta.tint} 14%, transparent)`, color: meta.tint }}
                    >
                        <Icon className="size-4" />
                    </span>
                    <span className="font-medium">{meta.name}</span>
                </div>
                <span className="text-xs text-muted-foreground">
                    {formatRange(data.from, data.to)}
                    {cut && data.hasStoreData && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Info className="ml-1 inline size-3.5 align-[-2px]" />
                            </TooltipTrigger>
                            <TooltipContent className="max-w-64">
                                The store has reported up to {formatDay(data.lastStoreDay ?? data.to)}, so every step here stops on that day.
                            </TooltipContent>
                        </Tooltip>
                    )}
                </span>
            </div>

            {!data.hasStoreData && (
                <p className="rounded-lg bg-muted/60 px-3 py-2 text-xs text-muted-foreground">
                    No store report covers this period yet. Store numbers arrive a few days late; the steps from “Opened the app” on are complete.
                </p>
            )}

            <div className="flex flex-col">
                <div className="grid grid-cols-[minmax(0,10rem)_minmax(0,1fr)_3.75rem_3.75rem] items-center gap-x-3 pb-1.5 text-[11px] font-medium text-muted-foreground">
                    <span>Step</span>
                    <span />
                    <span className="text-right">People</span>
                    <span className="text-right">Of above</span>
                </div>
                {data.steps.map((step, index) => {
                    const previous = data.steps.slice(0, index).reverse().find((candidate) => candidate.count !== null);
                    const conversion = step.count !== null && previous?.count ? share(step.count, previous.count) : null;
                    const inStore = STORE_STEPS.includes(step.key);
                    const startsApp = step.key === 'first_opens';

                    return (
                        <div key={step.key}>
                            {(index === 0 || startsApp) && (
                                <div className={cn('pb-1 text-[10px] font-semibold tracking-wider text-muted-foreground/80 uppercase', startsApp && 'mt-1 border-t border-dashed border-border pt-2')}>
                                    {inStore ? 'In the store' : 'In the app'}
                                </div>
                            )}
                            <div className="grid grid-cols-[minmax(0,10rem)_minmax(0,1fr)_3.75rem_3.75rem] items-center gap-x-3 py-1.5">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className="truncate text-sm">{STEP_LABEL[step.key]}</span>
                                    </TooltipTrigger>
                                    <TooltipContent className="max-w-64">{STEP_HINT[step.key][data.platform]}</TooltipContent>
                                </Tooltip>
                                <div className="h-5 rounded-[4px] bg-muted/50">
                                    {step.count !== null && (
                                        <div
                                            className={cn('h-full rounded-[4px]', meta.bar, !inStore && 'opacity-85')}
                                            style={{ width: step.count > 0 ? `max(3px, ${(step.count / top) * 100}%)` : 0 }}
                                        />
                                    )}
                                </div>
                                <span className={cn('text-right text-sm font-semibold tabular-nums', step.count === null && 'font-normal text-muted-foreground')}>
                                    {step.count === null ? '—' : formatNumber(step.count)}
                                </span>
                                <span className="text-right text-xs text-muted-foreground tabular-nums">
                                    {index === 0 || conversion === null ? '' : formatPercent(conversion)}
                                </span>
                            </div>
                        </div>
                    );
                })}
            </div>

            <p className="text-xs text-muted-foreground">
                {first && first.count ? (
                    <>
                        <span className="font-semibold text-foreground tabular-nums">{formatPercent(share(paid, first.count), 2)}</span> of people who{' '}
                        {first.key === 'impressions' ? 'saw the app' : first.key === 'page_views' ? 'opened the app page' : 'opened the app'} paid.
                    </>
                ) : (
                    'Nothing to compare yet.'
                )}
                {data.platform === 'android' && ' Google Play shares no impressions.'}
            </p>
        </div>
    );
}

export function StoreFunnel({ data, period }: { data: FunnelData; period: PeriodInfo }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>From the store to paying</CardTitle>
                <CardDescription>
                    How many people reached each step in the period, store by store. “Of above” is the share of the step before it.
                    {data.syncedAt && ` Store reports fetched ${new Date(data.syncedAt).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })}.`}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid grid-cols-1 gap-4 @4xl/main:grid-cols-2">
                {data.platforms.map((platform) => (
                    <PlatformFunnel key={platform.platform} data={platform} period={period} />
                ))}
            </CardContent>
        </Card>
    );
}
