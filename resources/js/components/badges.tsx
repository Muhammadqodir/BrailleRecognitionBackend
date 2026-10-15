import type { ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { countryFlag, countryName } from '@/lib/countries';

/** A small neutral tag for a code-like value (platform, provider, language). */
export function Pill({ children }: { children: ReactNode }) {
    return (
        <span className="inline-block rounded border border-border bg-muted/60 px-1.5 py-px text-[11px] font-medium text-muted-foreground">
            {children}
        </span>
    );
}

export function Empty() {
    return <span className="text-muted-foreground">—</span>;
}

export type Plan = 'paid' | 'trial' | 'free';

export const PLAN_LABELS: Record<Plan, string> = { paid: 'Paid', trial: 'Trial', free: 'Free' };

export function PlanBadge({ plan }: { plan: Plan }) {
    if (plan === 'free') {
        return <span className="text-muted-foreground">Free</span>;
    }

    return <Badge variant={plan === 'paid' ? 'default' : 'secondary'}>{PLAN_LABELS[plan]}</Badge>;
}

export function RatingMark({ rating }: { rating: number | null }) {
    if (!rating) {
        return <Empty />;
    }

    return rating > 0 ? <span title="Thumbs up">👍</span> : <span title="Thumbs down">👎</span>;
}

export function formatDate(value: string | null, fallback = '—'): string {
    return value ? new Date(value).toLocaleDateString() : fallback;
}

export function formatDateTime(value: string | null, fallback = 'Never'): string {
    return value ? new Date(value).toLocaleString() : fallback;
}

export function CountryCell({ code }: { code: string | null }) {
    if (!code) {
        return <Empty />;
    }

    return (
        <span className="flex min-w-0 items-center gap-1.5" title={countryName(code)}>
            <span>{countryFlag(code)}</span>
            <span className="truncate text-muted-foreground">{countryName(code)}</span>
        </span>
    );
}
