import { router } from '@inertiajs/react';
import { CalendarRange } from 'lucide-react';
import { useState } from 'react';

import { formatRange } from '@/components/dashboard/format';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export interface PeriodInfo {
    key: string;
    from: string;
    to: string;
    days: number;
    bucket: 'day' | 'week';
    previousFrom: string;
    previousTo: string;
    includesToday: boolean;
    today: string;
    timezone: string;
}

const PRESETS = [
    { key: 'today', label: 'Today' },
    { key: '7d', label: '7 days' },
    { key: '30d', label: '30 days' },
    { key: '90d', label: '90 days' },
    { key: '12m', label: '12 months' },
];

function visit(query: Record<string, string>, onLoading: (loading: boolean) => void) {
    router.get(window.location.pathname, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => onLoading(true),
        onFinish: () => onLoading(false),
    });
}

/** Preset ranges as a segmented control, plus a custom from–to range. */
export function PeriodPicker({ period, onLoading }: { period: PeriodInfo; onLoading: (loading: boolean) => void }) {
    const [open, setOpen] = useState(false);
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);
    const custom = period.key === 'custom';

    return (
        <div className="flex items-center gap-2">
            <div role="radiogroup" aria-label="Period" className="flex items-center rounded-lg bg-muted p-0.5">
                {PRESETS.map((preset) => {
                    const active = period.key === preset.key;

                    return (
                        <button
                            key={preset.key}
                            type="button"
                            role="radio"
                            aria-checked={active}
                            onClick={() => !active && visit({ range: preset.key }, onLoading)}
                            className={cn(
                                'h-7 rounded-md px-2.5 text-xs font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground',
                                active && 'bg-background text-foreground shadow-sm ring-1 ring-foreground/5',
                            )}
                        >
                            {preset.label}
                        </button>
                    );
                })}
            </div>

            <Popover
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);
                    if (next) {
                        setFrom(period.from);
                        setTo(period.to);
                    }
                }}
            >
                <PopoverTrigger asChild>
                    <Button variant={custom ? 'secondary' : 'outline'} size="sm" className="h-8">
                        <CalendarRange />
                        {custom ? formatRange(period.from, period.to) : 'Custom'}
                    </Button>
                </PopoverTrigger>
                <PopoverContent align="end" className="w-72">
                    <form
                        className="flex flex-col gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            setOpen(false);
                            visit({ from, to }, onLoading);
                        }}
                    >
                        <p className="text-sm font-medium">Custom range</p>
                        <div className="grid grid-cols-2 gap-2">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="period-from" className="text-xs text-muted-foreground">
                                    From
                                </Label>
                                <Input id="period-from" type="date" value={from} max={to || period.today} onChange={(e) => setFrom(e.target.value)} required />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="period-to" className="text-xs text-muted-foreground">
                                    To
                                </Label>
                                <Input id="period-to" type="date" value={to} min={from} max={period.today} onChange={(e) => setTo(e.target.value)} required />
                            </div>
                        </div>
                        <Button type="submit" size="sm">
                            Show
                        </Button>
                    </form>
                </PopoverContent>
            </Popover>
        </div>
    );
}
