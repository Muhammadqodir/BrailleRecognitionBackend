import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { NewTabLink } from '@/components/new-tab-link';
import { LanguageCard, PlatformCard, RoleCard, type NewUsersBy } from '@/components/dashboard/breakdowns';
import { CountryCard, type CountriesData } from '@/components/dashboard/country-card';
import { formatMoney, formatNumber, formatRange } from '@/components/dashboard/format';
import { GrowthChart, type GrowthPoint } from '@/components/dashboard/growth-chart';
import { KpiCard, type Kpi } from '@/components/dashboard/kpi-card';
import { PeriodPicker, type PeriodInfo } from '@/components/dashboard/period-picker';
import { StoreFunnel, type FunnelData } from '@/components/dashboard/store-funnel';
import { TranslationVolume, type TranslationVolumeData } from '@/components/dashboard/translation-volume';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import { countryFlag } from '@/lib/countries';
import { displayName, initials } from '@/lib/people';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface LanguageRow {
    lang: string;
    count: number;
    emptyRate: number;
    up: number;
    down: number;
}

interface SubscriptionEvent {
    id: number;
    type: string;
    periodType: string | null;
    price: number | null;
    country: string | null;
    productId: string | null;
    store: string | null;
    environment: string | null;
    userId: number | null;
    createdAt: string;
}

interface RecentUser {
    id: number;
    name: string | null;
    email: string | null;
    provider: string | null;
    role: string | null;
    createdAt: string | null;
}

interface DashboardProps {
    period: PeriodInfo;
    kpis: Kpi[];
    growth: GrowthPoint[];
    translationVolume: TranslationVolumeData;
    countries: CountriesData;
    newUsersBy: NewUsersBy;
    funnel: FunnelData;
    brailleLanguages: LanguageRow[];
    subscriptionEvents: SubscriptionEvent[];
    recentUsers: RecentUser[];
}

/** RevenueCat event types read as words: INITIAL_PURCHASE -> Initial purchase; a trial start says so. */
function eventLabel(event: SubscriptionEvent): string {
    if (event.type === 'INITIAL_PURCHASE' && event.periodType === 'TRIAL') {
        return 'Trial started';
    }
    if (event.type === 'NON_RENEWING_PURCHASE') {
        return 'Lifetime purchase';
    }

    const words = event.type.toLowerCase().replace(/_/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

function timeAgo(iso: string): string {
    const minutes = Math.round((Date.now() - Date.parse(iso)) / 60_000);

    if (minutes < 60) {
        return `${Math.max(1, minutes)} min ago`;
    }
    if (minutes < 60 * 24) {
        return `${Math.round(minutes / 60)} h ago`;
    }

    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

export default function Dashboard({ period, kpis, growth, translationVolume, countries, newUsersBy, funnel, brailleLanguages, subscriptionEvents, recentUsers }: DashboardProps) {
    const { routes } = usePage<SharedProps>().props;
    const [loading, setLoading] = useState(false);
    const buckets = growth.map((point) => point.date);
    const activeUsers = kpis.find((kpi) => kpi.key === 'active')?.value ?? 0;

    return (
        <DashboardLayout
            title="Dashboard"
            description="How the app is doing, for the period you pick"
            toolbar={
                <div className="flex w-full flex-wrap items-center justify-between gap-2 py-2">
                    <p className="text-xs text-muted-foreground">
                        <span className="font-medium text-foreground">{formatRange(period.from, period.to)}</span>
                        <span className="hidden sm:inline">
                            {' '}
                            · compared with {formatRange(period.previousFrom, period.previousTo)} · days in {period.timezone}
                        </span>
                    </p>
                    <PeriodPicker key={`${period.from}-${period.to}`} period={period} onLoading={setLoading} />
                </div>
            }
            toolbarClassName="min-h-11"
        >
            <Head title="Dashboard" />

            <div className={cn('@container/main flex flex-col gap-4 transition-opacity md:gap-6', loading && 'pointer-events-none opacity-60')} aria-busy={loading}>
                <div className="grid grid-cols-1 gap-4 @md/main:grid-cols-2 @3xl/main:grid-cols-3 @5xl/main:grid-cols-6">
                    {kpis.map((kpi) => (
                        <KpiCard key={kpi.key} kpi={kpi} period={period} buckets={buckets} />
                    ))}
                </div>

                <GrowthChart data={growth} period={period} />

                <CountryCard data={countries} />

                <div className="grid grid-cols-1 gap-4 @3xl/main:grid-cols-2 @5xl/main:grid-cols-3">
                    <PlatformCard data={newUsersBy.platform} />
                    <RoleCard rows={newUsersBy.role} />
                    <LanguageCard rows={newUsersBy.language} />
                </div>

                <div className="grid grid-cols-1 gap-4 @5xl/main:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <TranslationVolume data={translationVolume} period={period} activeUsers={activeUsers} />
                    <BrailleLanguages rows={brailleLanguages} translationsUrl={routes.translations} />
                </div>

                <StoreFunnel data={funnel} period={period} />

                <div className="grid grid-cols-1 gap-4 @5xl/main:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-3">
                            <div className="flex flex-col gap-1">
                                <CardTitle>Latest purchases</CardTitle>
                                <CardDescription>Newest events from RevenueCat, any period</CardDescription>
                            </div>
                            <NewTabLink href={routes.subscriptions} className="text-xs">
                                Subscriptions
                            </NewTabLink>
                        </CardHeader>
                        <CardContent>
                            {subscriptionEvents.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">No events yet.</p>
                            ) : (
                                <div className="flex flex-col divide-y divide-border/60">
                                    {subscriptionEvents.map((event) => (
                                        <div key={event.id} className="flex items-center gap-3 py-2.5">
                                            <span className={cn('size-2 shrink-0 rounded-full', event.store === 'APP_STORE' ? 'bg-viz-ios' : 'bg-viz-android')} />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2 text-sm font-medium">
                                                    <span className="truncate">{eventLabel(event)}</span>
                                                    {event.environment === 'SANDBOX' && (
                                                        <Badge variant="outline" className="h-5 px-1.5 text-[10px]">
                                                            sandbox
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {event.country && `${countryFlag(event.country)} `}
                                                    {event.productId ?? '—'}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 flex-col items-end">
                                                <span className="text-sm font-medium tabular-nums">{event.price ? formatMoney(event.price) : ''}</span>
                                                <span className="text-xs text-muted-foreground">{timeAgo(event.createdAt)}</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-3">
                            <div className="flex flex-col gap-1">
                                <CardTitle>Recent sign-ups</CardTitle>
                                <CardDescription>Newest accounts, any period</CardDescription>
                            </div>
                            <NewTabLink href={routes.users} className="text-xs">
                                Users
                            </NewTabLink>
                        </CardHeader>
                        <CardContent>
                            {recentUsers.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">No users yet.</p>
                            ) : (
                                <div className="flex flex-col divide-y divide-border/60">
                                    {recentUsers.map((user) => (
                                        <NewTabLink key={user.id} href={`${routes.users}?search=${user.id}`} icon={false} className="flex items-center gap-3 py-2.5 hover:no-underline">
                                            <Avatar className="size-8">
                                                <AvatarFallback className="text-xs">{initials(displayName(user))}</AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">{displayName(user)}</p>
                                                <p className="truncate text-xs text-muted-foreground">{user.role ?? user.email ?? `#${user.id}`}</p>
                                            </div>
                                            <span className="shrink-0 text-xs text-muted-foreground">{user.createdAt ? timeAgo(user.createdAt) : ''}</span>
                                        </NewTabLink>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    );
}

function BrailleLanguages({ rows, translationsUrl }: { rows: LanguageRow[]; translationsUrl: string }) {
    const max = Math.max(1, ...rows.map((row) => row.count));

    return (
        <Card>
            <CardHeader>
                <CardTitle>Braille languages</CardTitle>
                <CardDescription>Photos per language, how many found no text, and the thumbs</CardDescription>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <p className="py-8 text-center text-sm text-muted-foreground">No translations in this period.</p>
                ) : (
                    <div className="flex flex-col">
                        <div className="grid grid-cols-[minmax(0,1fr)_3.5rem_3.5rem_4.5rem] gap-x-3 border-b pb-2 text-[11px] font-medium text-muted-foreground">
                            <span>Language</span>
                            <span className="text-right">Photos</span>
                            <span className="text-right">No text</span>
                            <span className="text-right">👍 / 👎</span>
                        </div>
                        {rows.map((row) => (
                            <div key={row.lang} className="grid grid-cols-[minmax(0,1fr)_3.5rem_3.5rem_4.5rem] items-center gap-x-3 border-b border-border/50 py-2 last:border-b-0">
                                <div className="flex min-w-0 items-center gap-2.5">
                                    <NewTabLink href={`${translationsUrl}?lang[]=${row.lang}`} icon={false} className="w-10 shrink-0 font-mono text-xs font-medium">
                                        {row.lang}
                                    </NewTabLink>
                                    <div className="h-1.5 flex-1 rounded-full bg-muted">
                                        <div className="h-full rounded-full bg-viz-violet" style={{ width: `${Math.max(2, (row.count / max) * 100)}%` }} />
                                    </div>
                                </div>
                                <span className="text-right text-sm font-medium tabular-nums">{formatNumber(row.count)}</span>
                                <span className={cn('text-right text-xs tabular-nums', row.emptyRate >= 25 ? 'text-trend-down' : 'text-muted-foreground')}>{row.emptyRate}%</span>
                                <span className="text-right text-xs text-muted-foreground tabular-nums">
                                    {row.up} / {row.down}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
