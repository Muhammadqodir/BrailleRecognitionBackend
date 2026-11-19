import { ChevronDown } from 'lucide-react';
import { useState } from 'react';

import { formatNumber, formatPercent, share, trend } from '@/components/dashboard/format';
import { PlatformLegend } from '@/components/dashboard/growth-chart';
import { WorldMap } from '@/components/dashboard/world-map';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { countryFlag, countryName } from '@/lib/countries';
import { cn } from '@/lib/utils';

export interface CountryRow {
    code: string;
    installs: number;
    ios: number;
    android: number;
    previous: number;
    trials: number;
}

export interface CountriesData {
    rows: CountryRow[];
    unplaced: number;
    total: number;
}

const COLLAPSED = 10;

function Change({ value, previous }: { value: number; previous: number }) {
    const change = trend(value, previous);

    return (
        <span
            className={cn(
                'text-xs tabular-nums',
                change.direction === 'up' && 'text-trend-up',
                change.direction === 'down' && 'text-trend-down',
                (change.direction === 'flat' || change.direction === 'new') && 'text-muted-foreground',
            )}
        >
            {change.direction === 'new' ? 'new' : change.text}
        </span>
    );
}

export function CountryCard({ data }: { data: CountriesData }) {
    const [hovered, setHovered] = useState<string | null>(null);
    const [expanded, setExpanded] = useState(false);
    const rows = expanded ? data.rows : data.rows.slice(0, COLLAPSED);
    const max = Math.max(1, ...data.rows.map((row) => row.installs));

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <CardTitle>New users by country</CardTitle>
                    <CardDescription>
                        {formatNumber(data.total)} new installs in {formatNumber(data.rows.length)} countries, from the region in the phone's language
                    </CardDescription>
                </div>
                <PlatformLegend />
            </CardHeader>
            <CardContent className="grid grid-cols-1 gap-6 @4xl/main:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
                <div className="flex flex-col gap-2 self-center">
                    <WorldMap countries={data.rows.map((row) => ({ code: row.code, count: row.installs }))} hovered={hovered} onHover={setHovered} />
                    {data.unplaced > 0 && (
                        <p className="text-xs text-muted-foreground">
                            {formatNumber(data.unplaced)} more {data.unplaced === 1 ? 'install has' : 'installs have'} no country: the phone's language has no region (e.g. plain “en”).
                        </p>
                    )}
                </div>

                {data.rows.length === 0 ? (
                    <p className="py-10 text-center text-sm text-muted-foreground">No new installs in this period.</p>
                ) : (
                    <div className="flex min-w-0 flex-col">
                        <div className="grid grid-cols-[minmax(0,1fr)_3.5rem_3.25rem_3rem] items-center gap-x-3 border-b pb-2 text-[11px] font-medium text-muted-foreground">
                            <span>Country</span>
                            <span className="text-right">Installs</span>
                            <span className="text-right">Change</span>
                            <span className="text-right">Trials</span>
                        </div>
                        {rows.map((row) => (
                            <div
                                key={row.code}
                                onMouseEnter={() => setHovered(row.code)}
                                onMouseLeave={() => setHovered(null)}
                                className={cn(
                                    'grid grid-cols-[minmax(0,1fr)_3.5rem_3.25rem_3rem] items-center gap-x-3 rounded-md border-b border-border/50 py-2 transition-colors last:border-b-0',
                                    hovered === row.code && 'bg-muted/60',
                                )}
                            >
                                <div className="flex min-w-0 flex-col gap-1.5 pl-1">
                                    <div className="flex min-w-0 items-baseline justify-between gap-2 text-sm">
                                        <span className="truncate font-medium">
                                            {countryFlag(row.code)} {countryName(row.code)}
                                        </span>
                                        <span className="shrink-0 text-xs text-muted-foreground tabular-nums">{formatPercent(share(row.installs, data.total))}</span>
                                    </div>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <div className="flex h-1.5 overflow-hidden rounded-full bg-muted" style={{ width: `${Math.max(4, (row.installs / max) * 100)}%` }}>
                                                <div className="h-full bg-viz-ios" style={{ width: `${share(row.ios, row.installs)}%` }} />
                                                <div className="h-full bg-viz-android" style={{ width: `${share(row.android, row.installs)}%` }} />
                                            </div>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            iOS {formatNumber(row.ios)} · Android {formatNumber(row.android)}
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                                <span className="text-right text-sm font-medium tabular-nums">{formatNumber(row.installs)}</span>
                                <span className="text-right">
                                    <Change value={row.installs} previous={row.previous} />
                                </span>
                                <span className={cn('text-right text-sm tabular-nums', row.trials === 0 && 'text-muted-foreground/60')}>{row.trials}</span>
                            </div>
                        ))}
                        {data.rows.length > COLLAPSED && (
                            <Button variant="ghost" size="sm" className="mt-2 self-start text-muted-foreground" onClick={() => setExpanded((value) => !value)}>
                                <ChevronDown className={cn('transition-transform', expanded && 'rotate-180')} />
                                {expanded ? 'Show top 10' : `Show all ${data.rows.length} countries`}
                            </Button>
                        )}
                        <p className="mt-2 text-[11px] text-muted-foreground">Trials use the store account's country, so they can sit in a country with no installs listed.</p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
