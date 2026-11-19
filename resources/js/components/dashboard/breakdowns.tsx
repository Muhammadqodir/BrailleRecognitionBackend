import { formatNumber, formatPercent, languageName, share, trend } from '@/components/dashboard/format';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export interface NewUsersBy {
    platform: { key: 'ios' | 'android'; count: number; previous: number }[];
    role: { key: string | null; count: number }[];
    language: { key: string | null; count: number }[];
}

const PLATFORM_LABEL = { ios: 'iOS', android: 'Android' } as const;
const PLATFORM_COLOR = { ios: 'bg-viz-ios', android: 'bg-viz-android' } as const;
const RANKED_LIMIT = 7;

/** iOS vs Android as one split bar, with each side's count and change. */
export function PlatformCard({ data }: { data: NewUsersBy['platform'] }) {
    const total = data.reduce((sum, row) => sum + row.count, 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle>New users by platform</CardTitle>
                <CardDescription>{formatNumber(total)} new installs</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
                <div className="flex h-9 gap-0.5 overflow-hidden rounded-lg">
                    {total === 0 ? (
                        <div className="flex-1 bg-muted" />
                    ) : (
                        data
                            .filter((row) => row.count > 0)
                            .map((row) => (
                                <div
                                    key={row.key}
                                    className={cn('flex items-center px-2.5 text-xs font-semibold text-white', PLATFORM_COLOR[row.key])}
                                    style={{ width: `${share(row.count, total)}%` }}
                                >
                                    {share(row.count, total) >= 14 && formatPercent(share(row.count, total), 0)}
                                </div>
                            ))
                    )}
                </div>
                <div className="flex flex-col gap-3">
                    {data.map((row) => {
                        const change = trend(row.count, row.previous);

                        return (
                            <div key={row.key} className="flex items-center gap-3">
                                <span className={cn('size-2.5 shrink-0 rounded-[3px]', PLATFORM_COLOR[row.key])} />
                                <span className="flex-1 text-sm font-medium">{PLATFORM_LABEL[row.key]}</span>
                                <span className="text-sm font-semibold tabular-nums">{formatNumber(row.count)}</span>
                                <span
                                    className={cn(
                                        'w-14 text-right text-xs tabular-nums',
                                        change.direction === 'up' && 'text-trend-up',
                                        change.direction === 'down' && 'text-trend-down',
                                        (change.direction === 'flat' || change.direction === 'new') && 'text-muted-foreground',
                                    )}
                                >
                                    {change.text}
                                </span>
                            </div>
                        );
                    })}
                    <p className="text-[11px] text-muted-foreground">Change against the previous period.</p>
                </div>
            </CardContent>
        </Card>
    );
}

/** A ranked list with a bar per row; rows past the limit fold into "Other". */
export function RankedCard({
    title,
    description,
    rows,
    label,
}: {
    title: string;
    description: string;
    rows: { key: string | null; count: number }[];
    label: (key: string | null) => string;
}) {
    const total = rows.reduce((sum, row) => sum + row.count, 0);
    const known = rows.filter((row) => row.key !== null);
    const unknown = rows.find((row) => row.key === null);
    const shown = known.slice(0, RANKED_LIMIT);
    const rest = known.slice(RANKED_LIMIT).reduce((sum, row) => sum + row.count, 0);
    const list: { key: string; name: string; count: number; muted?: boolean }[] = [
        ...shown.map((row) => ({ key: row.key as string, name: label(row.key), count: row.count })),
        ...(rest > 0 ? [{ key: '__other', name: `Other (${known.length - RANKED_LIMIT})`, count: rest, muted: true }] : []),
        ...(unknown && unknown.count > 0 ? [{ key: '__unknown', name: label(null), count: unknown.count, muted: true }] : []),
    ];
    const max = Math.max(1, ...list.map((row) => row.count));

    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                {list.length === 0 ? (
                    <p className="py-8 text-center text-sm text-muted-foreground">No new users in this period.</p>
                ) : (
                    <div className="flex flex-col gap-2.5">
                        {list.map((row) => (
                            <div key={row.key} className="flex flex-col gap-1">
                                <div className="flex items-baseline justify-between gap-3 text-sm">
                                    <span className={cn('min-w-0 truncate', row.muted ? 'text-muted-foreground' : 'font-medium')} title={row.name}>
                                        {row.name}
                                    </span>
                                    <span className="shrink-0 tabular-nums">
                                        <span className="font-medium">{formatNumber(row.count)}</span>
                                        <span className="ml-1.5 inline-block w-10 text-right text-xs text-muted-foreground">{formatPercent(share(row.count, total), 0)}</span>
                                    </span>
                                </div>
                                <div className="h-1.5 rounded-full bg-muted">
                                    <div
                                        className={cn('h-full rounded-full', row.muted ? 'bg-muted-foreground/35' : 'bg-foreground/70')}
                                        style={{ width: `${Math.max(1.5, (row.count / max) * 100)}%` }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export function RoleCard({ rows }: { rows: NewUsersBy['role'] }) {
    return <RankedCard title="New users by type" description="The role picked in onboarding" rows={rows} label={(key) => key ?? 'Not answered'} />;
}

export function LanguageCard({ rows }: { rows: NewUsersBy['language'] }) {
    return <RankedCard title="New users by language" description="The phone's language" rows={rows} label={languageName} />;
}
