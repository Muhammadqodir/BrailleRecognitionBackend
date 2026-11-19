import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';

import { formatDay, formatNumber, formatPercent, share } from '@/components/dashboard/format';
import type { PeriodInfo } from '@/components/dashboard/period-picker';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';

export interface TranslationVolumeData {
    points: { date: string; found: number; empty: number }[];
    total: number;
    found: number;
}

const config = {
    found: { label: 'Text found', color: 'var(--viz-violet)' },
    empty: { label: 'No text', color: 'var(--viz-none)' },
} satisfies ChartConfig;

export function TranslationVolume({ data, period, activeUsers }: { data: TranslationVolumeData; period: PeriodInfo; activeUsers: number }) {
    const label = (value: unknown) => `${period.bucket === 'week' ? 'Week of ' : ''}${formatDay(String(value), true)}`;
    const perActive = activeUsers > 0 ? data.total / activeUsers : 0;

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <CardTitle>Translations</CardTitle>
                    <CardDescription>Photos read {period.bucket === 'week' ? 'each week' : 'each day'}</CardDescription>
                </div>
                <div className="flex items-center gap-3 text-xs text-muted-foreground">
                    <span className="flex items-center gap-1.5">
                        <span className="size-2.5 rounded-[3px] bg-viz-violet" /> Text found
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="size-2.5 rounded-[3px] bg-viz-none" /> No text
                    </span>
                </div>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <div className="grid grid-cols-3 gap-2">
                    <Figure label="Photos" value={formatNumber(data.total)} />
                    <Figure label="Found text" value={formatPercent(share(data.found, data.total))} />
                    <Figure label="Per active user" value={perActive.toFixed(1)} />
                </div>
                <ChartContainer config={config} className="aspect-auto h-[220px] w-full">
                    <BarChart data={data.points} margin={{ top: 4, right: 4, bottom: 0, left: -12 }} barCategoryGap="18%">
                        <CartesianGrid vertical={false} stroke="var(--border)" />
                        <YAxis tickLine={false} axisLine={false} width={40} allowDecimals={false} tickCount={4} fontSize={11} />
                        <XAxis dataKey="date" tickLine={false} axisLine={false} tickMargin={8} minTickGap={28} tickFormatter={(value) => formatDay(String(value))} fontSize={11} />
                        <ChartTooltip cursor={{ fill: 'var(--muted)', opacity: 0.6 }} content={<ChartTooltipContent labelFormatter={label} />} />
                        <Bar dataKey="found" stackId="a" fill="var(--color-found)" stroke="var(--card)" strokeWidth={1} isAnimationActive={false} />
                        <Bar dataKey="empty" stackId="a" fill="var(--color-empty)" stroke="var(--card)" strokeWidth={1} radius={[3, 3, 0, 0]} isAnimationActive={false} />
                    </BarChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}

export function Figure({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex flex-col gap-0.5 rounded-lg bg-muted/50 px-3 py-2">
            <span className="text-[11px] text-muted-foreground">{label}</span>
            <span className="text-base font-semibold tabular-nums">{value}</span>
        </div>
    );
}
