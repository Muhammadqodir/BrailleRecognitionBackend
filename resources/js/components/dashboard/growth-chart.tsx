import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';

import { formatDay, formatNumber, formatPercent, share } from '@/components/dashboard/format';
import type { PeriodInfo } from '@/components/dashboard/period-picker';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';

export interface GrowthPoint {
    date: string;
    installsIos: number;
    installsAndroid: number;
    trialsIos: number;
    trialsAndroid: number;
}

const installsConfig = {
    installsIos: { label: 'iOS', color: 'var(--viz-ios)' },
    installsAndroid: { label: 'Android', color: 'var(--viz-android)' },
} satisfies ChartConfig;

const trialsConfig = {
    trialsIos: { label: 'iOS', color: 'var(--viz-ios)' },
    trialsAndroid: { label: 'Android', color: 'var(--viz-android)' },
} satisfies ChartConfig;

export function PlatformLegend() {
    return (
        <div className="flex items-center gap-3 text-xs text-muted-foreground">
            <span className="flex items-center gap-1.5">
                <span className="size-2.5 rounded-[3px] bg-viz-ios" /> iOS
            </span>
            <span className="flex items-center gap-1.5">
                <span className="size-2.5 rounded-[3px] bg-viz-android" /> Android
            </span>
        </div>
    );
}

function Panel({
    title,
    total,
    data,
    config,
    keys,
    height,
    period,
}: {
    title: string;
    total: number;
    data: GrowthPoint[];
    config: ChartConfig;
    keys: [keyof GrowthPoint, keyof GrowthPoint];
    height: number;
    period: PeriodInfo;
}) {
    const label = (value: unknown) => `${period.bucket === 'week' ? 'Week of ' : ''}${formatDay(String(value), true)}`;

    return (
        <div className="flex flex-col gap-1">
            <div className="flex items-baseline gap-2 pl-1">
                <span className="text-xs font-medium text-muted-foreground">{title}</span>
                <span className="text-sm font-semibold tabular-nums">{formatNumber(total)}</span>
            </div>
            <ChartContainer config={config} className="aspect-auto w-full" style={{ height }}>
                <BarChart data={data} syncId="growth" margin={{ top: 4, right: 4, bottom: 0, left: -12 }} barCategoryGap="18%">
                    <CartesianGrid vertical={false} stroke="var(--border)" />
                    <YAxis tickLine={false} axisLine={false} width={40} allowDecimals={false} tickCount={4} fontSize={11} />
                    <XAxis dataKey="date" tickLine={false} axisLine={false} tickMargin={8} minTickGap={28} tickFormatter={(value) => formatDay(String(value))} fontSize={11} />
                    <ChartTooltip cursor={{ fill: 'var(--muted)', opacity: 0.6 }} content={<ChartTooltipContent labelFormatter={label} />} />
                    <Bar dataKey={keys[0]} stackId="a" fill={`var(--color-${keys[0]})`} stroke="var(--card)" strokeWidth={1} isAnimationActive={false} />
                    <Bar dataKey={keys[1]} stackId="a" fill={`var(--color-${keys[1]})`} stroke="var(--card)" strokeWidth={1} radius={[3, 3, 0, 0]} isAnimationActive={false} />
                </BarChart>
            </ChartContainer>
        </div>
    );
}

/**
 * New installs and trial starts on two aligned charts: trials are a small
 * fraction of installs, and one shared axis would flatten them.
 */
export function GrowthChart({ data, period }: { data: GrowthPoint[]; period: PeriodInfo }) {
    const installs = data.reduce((sum, point) => sum + point.installsIos + point.installsAndroid, 0);
    const trials = data.reduce((sum, point) => sum + point.trialsIos + point.trialsAndroid, 0);

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <CardTitle>New installs and trials</CardTitle>
                    <CardDescription>{period.bucket === 'week' ? 'Week by week' : 'Day by day'}, by platform</CardDescription>
                </div>
                <div className="flex flex-col items-end gap-1.5">
                    <div className="rounded-lg bg-muted/60 px-2.5 py-1 text-xs">
                        <span className="font-semibold tabular-nums">{formatPercent(share(trials, installs))}</span>
                        <span className="text-muted-foreground"> of new installs started a trial</span>
                    </div>
                    <PlatformLegend />
                </div>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <Panel title="New installs" total={installs} data={data} config={installsConfig} keys={['installsIos', 'installsAndroid']} height={210} period={period} />
                <Panel title="Trials started" total={trials} data={data} config={trialsConfig} keys={['trialsIos', 'trialsAndroid']} height={130} period={period} />
            </CardContent>
        </Card>
    );
}
