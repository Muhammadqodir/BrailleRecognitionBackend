import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

export function DetailSection({ title, children, className }: { title: string; children: ReactNode; className?: string }) {
    return (
        <div className={cn('flex flex-col gap-3', className)}>
            <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{title}</h3>
            <div className="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">{children}</div>
        </div>
    );
}

export function DetailField({ label, value, className }: { label: string; value: ReactNode; className?: string }) {
    return (
        <div className={cn('flex min-w-0 flex-col gap-0.5', className)}>
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className="truncate text-sm font-medium text-foreground">
                {value === null || value === undefined || value === '' ? <span className="text-muted-foreground">—</span> : value}
            </span>
        </div>
    );
}

export function DetailStat({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex flex-col items-center gap-1 rounded-lg border border-border bg-muted/30 py-3">
            <span className="text-xl font-semibold tabular-nums text-foreground">{value}</span>
            <span className="text-xs text-muted-foreground">{label}</span>
        </div>
    );
}
