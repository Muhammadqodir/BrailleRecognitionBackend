import { Head, Link, usePage } from '@inertiajs/react';
import { Minus, Receipt, TrendingDown, TrendingUp } from 'lucide-react';
import { Area, AreaChart, Bar, BarChart, Cell, Pie, PieChart, XAxis } from 'recharts';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { countryName, WorldMap } from '@/components/dashboard/world-map';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import { displayName, initials } from '@/lib/people';
import type { SharedProps } from '@/types';

interface Kpi {
    value: number | null;
    delta: number | null;
}

interface DailyPoint {
    date: string;
    count: number;
}

interface TranslationPoint {
    date: string;
    device: number;
    server: number;
}

interface PlatformPoint {
    platform: string;
    count: number;
}

interface LanguageRow {
    lang: string;
    count: number;
    emptyRate: number;
    up: number;
    down: number;
}

interface FunnelStep {
    key: string;
    label: string;
    count: number;
}

interface SubscriptionEvent {
    id: number;
    type: string;
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
    kpis: {
        totalUsers: Kpi;
        signedInUsers: Kpi;
        activeToday: Kpi;
        activeThisWeek: Kpi;
        newThisWeek: Kpi;
        translationsThisWeek: Kpi;
        totalTranslations: Kpi;
        paidSubscribers: Kpi;
        trials: Kpi;
        readRate: Kpi;
        thumbsUp: Kpi;
        thumbsDown: Kpi;
    };
    userGrowth: DailyPoint[];
    activeInstalls: DailyPoint[];
    platforms: PlatformPoint[];
    installsByCountry: { countries: { code: string; count: number }[]; unplacedInstalls: number };
    translationsDaily: TranslationPoint[];
    topLanguages: LanguageRow[];
    funnel: { cohort: number; steps: FunnelStep[] };
    subscriptionEvents: SubscriptionEvent[];
    recentUsers: RecentUser[];
}

type Direction = 'up' | 'down' | 'neutral';

const numberFormatter = new Intl.NumberFormat('en-US');
const barShades = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];

function formatShortDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function trendFromDelta(delta: number | null, suffix = '%'): { text: string; direction: Direction } {
    if (delta === null) {
        return { text: '—', direction: 'neutral' };
    }

    return {
        text: `${delta > 0 ? '+' : ''}${delta}${suffix}`,
        direction: delta > 0 ? 'up' : delta < 0 ? 'down' : 'neutral',
    };
}

function trendHeadline(delta: number | null, suffix: string): string {
    if (delta === null) {
        return 'No prior-week data';
    }

    return `${delta >= 0 ? 'Up' : 'Down'} ${Math.abs(delta)}${suffix}`;
}

/** RevenueCat event types read as words: INITIAL_PURCHASE -> Initial purchase. */
function eventLabel(type: string): string {
    const words = type.toLowerCase().replace(/_/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

const growthConfig = { count: { label: 'New users', color: 'var(--chart-1)' } } satisfies ChartConfig;
const activeConfig = { count: { label: 'Active installs', color: 'var(--chart-1)' } } satisfies ChartConfig;
const translationsConfig = {
    device: { label: 'On the phone', color: 'var(--chart-1)' },
    server: { label: 'OCR server', color: 'var(--chart-3)' },
} satisfies ChartConfig;

function TrendBadge({ direction, text }: { direction: Direction; text: string }) {
    const Icon = direction === 'up' ? TrendingUp : direction === 'down' ? TrendingDown : Minus;

    return (
        <Badge variant="secondary" className="gap-1 font-normal text-muted-foreground">
            <Icon className="size-3" />
            {text}
        </Badge>
    );
}

function StatCard({
    label,
    value,
    badge,
    headline,
    headlineIcon,
    description,
}: {
    label: string;
    value: string;
    badge: { text: string; direction: Direction };
    headline: string;
    headlineIcon?: Direction;
    description?: string;
}) {
    const HeadlineIcon = headlineIcon === 'up' ? TrendingUp : headlineIcon === 'down' ? TrendingDown : null;

    return (
        <Card>
            <CardContent className="flex flex-col gap-3">
                <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">{label}</span>
                    <TrendBadge direction={badge.direction} text={badge.text} />
                </div>
                <div className="text-3xl font-semibold tracking-tight tabular-nums">{value}</div>
                <div className="space-y-0.5">
                    <p className="flex items-center gap-1 text-sm font-medium">
                        {headline}
                        {HeadlineIcon && <HeadlineIcon className="size-3.5" />}
                    </p>
                    {description && <p className="text-sm text-muted-foreground">{description}</p>}
                </div>
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    kpis,
    userGrowth,
    activeInstalls,
    platforms,
    installsByCountry,
    translationsDaily,
    topLanguages,
    funnel,
    subscriptionEvents,
    recentUsers,
}: DashboardProps) {
    const { routes } = usePage<SharedProps>().props;
    const platformConfig = Object.fromEntries(
        platforms.map((entry, index) => [entry.platform, { label: entry.platform, color: barShades[index % barShades.length] }]),
    ) satisfies ChartConfig;
    const activeTrend = trendFromDelta(kpis.activeThisWeek.delta);
    const newTrend = trendFromDelta(kpis.newThisWeek.delta);
    const translationsTrend = trendFromDelta(kpis.translationsThisWeek.delta);
    const readTrend = trendFromDelta(kpis.readRate.delta, ' pt');
    const thumbsUp = kpis.thumbsUp.value ?? 0;
    const thumbsDown = kpis.thumbsDown.value ?? 0;
    const topCountries = installsByCountry.countries.slice(0, 8);

    return (
        <DashboardLayout title="Dashboard" description="Overview of the app's activity and growth">
            <Head title="Dashboard" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Total users"
                    value={numberFormatter.format(kpis.totalUsers.value ?? 0)}
                    badge={{ text: 'All time', direction: 'neutral' }}
                    headline={`${numberFormatter.format(kpis.signedInUsers.value ?? 0)} signed in`}
                    description="accounts, anonymous ones included"
                />
                <StatCard
                    label="Active today"
                    value={numberFormatter.format(kpis.activeToday.value ?? 0)}
                    badge={{ text: 'Live', direction: 'neutral' }}
                    headline="Seen since midnight"
                    description={`${numberFormatter.format(kpis.activeThisWeek.value ?? 0)} active in the last 7 days`}
                />
                <StatCard
                    label="Active this week"
                    value={numberFormatter.format(kpis.activeThisWeek.value ?? 0)}
                    badge={activeTrend}
                    headline={trendHeadline(kpis.activeThisWeek.delta, '% this week')}
                    headlineIcon={activeTrend.direction}
                    description="vs. the previous 7 days"
                />
                <StatCard
                    label="New this week"
                    value={numberFormatter.format(kpis.newThisWeek.value ?? 0)}
                    badge={newTrend}
                    headline={trendHeadline(kpis.newThisWeek.delta, '% vs last week')}
                    headlineIcon={newTrend.direction}
                    description="new accounts"
                />
                <StatCard
                    label="Translations this week"
                    value={numberFormatter.format(kpis.translationsThisWeek.value ?? 0)}
                    badge={translationsTrend}
                    headline={trendHeadline(kpis.translationsThisWeek.delta, '% vs last week')}
                    headlineIcon={translationsTrend.direction}
                    description={`${numberFormatter.format(kpis.totalTranslations.value ?? 0)} photos read all time`}
                />
                <StatCard
                    label="Paying subscribers"
                    value={numberFormatter.format(kpis.paidSubscribers.value ?? 0)}
                    badge={{ text: `${numberFormatter.format(kpis.trials.value ?? 0)} in trial`, direction: 'neutral' }}
                    headline="Active store purchases"
                    description="sandbox and promo grants left out"
                />
                <StatCard
                    label="Read success rate"
                    value={kpis.readRate.value === null ? '—' : `${kpis.readRate.value}%`}
                    badge={readTrend}
                    headline={trendHeadline(kpis.readRate.delta, ' pts this week')}
                    headlineIcon={readTrend.direction}
                    description="of scans found text, last 7 days"
                />
                <StatCard
                    label="Result ratings"
                    value={numberFormatter.format(thumbsUp + thumbsDown)}
                    badge={{ text: 'Last 30 days', direction: 'neutral' }}
                    headline={thumbsUp + thumbsDown === 0 ? 'No ratings yet' : `${thumbsUp} up · ${thumbsDown} down`}
                    description="thumbs on translation results"
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Installs by country</CardTitle>
                    <CardDescription>Where each install is, from the region in its device language</CardDescription>
                </CardHeader>
                <CardContent className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <WorldMap countries={installsByCountry.countries} unplacedInstalls={installsByCountry.unplacedInstalls} />
                    </div>
                    {topCountries.length === 0 ? (
                        <p className="py-10 text-center text-sm text-muted-foreground">No installs yet.</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Country</TableHead>
                                    <TableHead className="text-right">Installs</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {topCountries.map((country) => (
                                    <TableRow key={country.code}>
                                        <TableCell className="font-medium">{countryName(country.code)}</TableCell>
                                        <TableCell className="text-right tabular-nums">{numberFormatter.format(country.count)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-7">
                <Card className="lg:col-span-4">
                    <CardHeader>
                        <CardTitle>User growth</CardTitle>
                        <CardDescription>New accounts, last 30 days</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ChartContainer config={growthConfig} className="aspect-auto h-[280px] w-full">
                            <AreaChart data={userGrowth} margin={{ left: 0, right: 0, top: 8, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="fillGrowth" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="var(--color-count)" stopOpacity={0.35} />
                                        <stop offset="95%" stopColor="var(--color-count)" stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <XAxis dataKey="date" tickLine={false} axisLine={false} tickMargin={10} minTickGap={32} tickFormatter={formatShortDate} />
                                <ChartTooltip
                                    cursor={{ stroke: 'var(--border)', strokeWidth: 1 }}
                                    content={<ChartTooltipContent labelFormatter={(value) => formatShortDate(String(value))} />}
                                />
                                <Area dataKey="count" type="monotone" fill="url(#fillGrowth)" stroke="var(--color-count)" strokeWidth={2} />
                            </AreaChart>
                        </ChartContainer>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-3">
                    <CardHeader>
                        <CardTitle>Recent signups</CardTitle>
                        <CardDescription>{recentUsers.length} most recent accounts</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentUsers.length === 0 ? (
                            <p className="py-10 text-center text-sm text-muted-foreground">No users yet.</p>
                        ) : (
                            <div className="flex flex-col gap-5">
                                {recentUsers.map((user) => (
                                    <Link key={user.id} href={`${routes.users}?search=${user.id}`} className="flex items-center gap-3">
                                        <Avatar>
                                            <AvatarFallback>{initials(displayName(user))}</AvatarFallback>
                                        </Avatar>
                                        <div className="min-w-0 flex-1 space-y-0.5">
                                            <p className="truncate text-sm leading-none font-medium">{displayName(user)}</p>
                                            <p className="truncate text-sm text-muted-foreground">{user.email ?? user.role ?? `#${user.id}`}</p>
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {user.createdAt ? new Date(user.createdAt).toLocaleDateString() : ''}
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Active installs</CardTitle>
                        <CardDescription>Distinct installs that sent any event, last 30 days</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ChartContainer config={activeConfig} className="aspect-auto h-[250px] w-full">
                            <AreaChart data={activeInstalls} margin={{ left: 0, right: 0, top: 8, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="fillActive" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="var(--color-count)" stopOpacity={0.35} />
                                        <stop offset="95%" stopColor="var(--color-count)" stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <XAxis dataKey="date" tickLine={false} axisLine={false} tickMargin={10} minTickGap={32} tickFormatter={formatShortDate} />
                                <ChartTooltip
                                    cursor={{ stroke: 'var(--border)', strokeWidth: 1 }}
                                    content={<ChartTooltipContent labelFormatter={(value) => formatShortDate(String(value))} />}
                                />
                                <Area dataKey="count" type="monotone" fill="url(#fillActive)" stroke="var(--color-count)" strokeWidth={2} />
                            </AreaChart>
                        </ChartContainer>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Platform breakdown</CardTitle>
                        <CardDescription>Active installs by platform, last 30 days</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {platforms.length === 0 ? (
                            <p className="py-10 text-center text-sm text-muted-foreground">No platform data yet.</p>
                        ) : (
                            <ChartContainer config={platformConfig} className="mx-auto aspect-square max-h-[250px]">
                                <PieChart>
                                    <ChartTooltip content={<ChartTooltipContent nameKey="platform" hideLabel />} />
                                    <Pie data={platforms} dataKey="count" nameKey="platform" innerRadius={55} strokeWidth={4}>
                                        {platforms.map((entry, index) => (
                                            <Cell key={entry.platform} fill={barShades[index % barShades.length]} />
                                        ))}
                                    </Pie>
                                    <ChartLegend content={<ChartLegendContent nameKey="platform" />} />
                                </PieChart>
                            </ChartContainer>
                        )}
                    </CardContent>
                </Card>
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Translations</CardTitle>
                        <CardDescription>Read on the phone vs. on the OCR server, last 14 days</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ChartContainer config={translationsConfig} className="aspect-auto h-[250px] w-full">
                            <BarChart data={translationsDaily} margin={{ left: 0, right: 0, top: 8, bottom: 0 }} barSize={18}>
                                <XAxis dataKey="date" tickLine={false} axisLine={false} tickMargin={10} minTickGap={24} tickFormatter={formatShortDate} />
                                <ChartTooltip
                                    cursor={{ fill: 'var(--muted)' }}
                                    content={<ChartTooltipContent labelFormatter={(value) => formatShortDate(String(value))} />}
                                />
                                <ChartLegend content={<ChartLegendContent />} />
                                <Bar dataKey="device" stackId="a" fill="var(--color-device)" radius={[0, 0, 6, 6]} />
                                <Bar dataKey="server" stackId="a" fill="var(--color-server)" radius={[6, 6, 0, 0]} />
                            </BarChart>
                        </ChartContainer>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Top languages</CardTitle>
                        <CardDescription>Translations by braille language, last 30 days</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {topLanguages.length === 0 ? (
                            <p className="py-10 text-center text-sm text-muted-foreground">No translations yet.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Language</TableHead>
                                        <TableHead className="text-right">Scans</TableHead>
                                        <TableHead className="text-right">Empty</TableHead>
                                        <TableHead className="text-right">Up / down</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {topLanguages.map((language) => (
                                        <TableRow key={language.lang}>
                                            <TableCell className="font-medium">
                                                <Link href={`${routes.translations}?lang[]=${language.lang}`} className="hover:underline">
                                                    {language.lang}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{numberFormatter.format(language.count)}</TableCell>
                                            <TableCell className="text-right tabular-nums text-muted-foreground">{language.emptyRate}%</TableCell>
                                            <TableCell className="text-right tabular-nums text-muted-foreground">
                                                {language.up} / {language.down}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>New install funnel</CardTitle>
                        <CardDescription>
                            How far the {numberFormatter.format(funnel.cohort)} installs first opened in the last 30 days got
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {funnel.cohort === 0 ? (
                            <p className="py-10 text-center text-sm text-muted-foreground">No new installs yet.</p>
                        ) : (
                            <div className="flex flex-col gap-4">
                                {funnel.steps.map((step) => {
                                    const share = Math.round((step.count / funnel.cohort) * 1000) / 10;

                                    return (
                                        <div key={step.key} className="flex flex-col gap-1.5">
                                            <div className="flex items-baseline justify-between gap-3 text-sm">
                                                <span className="font-medium">{step.label}</span>
                                                <span className="tabular-nums text-muted-foreground">
                                                    {numberFormatter.format(step.count)} · {share}%
                                                </span>
                                            </div>
                                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                <div className="h-full rounded-full bg-chart-1" style={{ width: `${Math.min(100, share)}%` }} />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Subscription events</CardTitle>
                        <CardDescription>Latest from the RevenueCat webhook</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Receipt className="size-4" />
                            {numberFormatter.format(kpis.paidSubscribers.value ?? 0)} paying · {numberFormatter.format(kpis.trials.value ?? 0)} in trial
                            <Link href={routes.subscriptions} className="ml-auto text-foreground hover:underline">
                                View all
                            </Link>
                        </div>
                        {subscriptionEvents.length === 0 ? (
                            <p className="py-10 text-center text-sm text-muted-foreground">No events yet.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Event</TableHead>
                                        <TableHead>Product</TableHead>
                                        <TableHead className="text-right">When</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {subscriptionEvents.map((event) => (
                                        <TableRow key={event.id}>
                                            <TableCell className="font-medium">
                                                {eventLabel(event.type)}
                                                {event.environment === 'SANDBOX' && (
                                                    <Badge variant="outline" className="ml-2">
                                                        sandbox
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">{event.productId ?? '—'}</TableCell>
                                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                                {new Date(event.createdAt).toLocaleDateString()}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </DashboardLayout>
    );
}
