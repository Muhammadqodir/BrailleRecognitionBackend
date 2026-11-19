export const numberFormat = new Intl.NumberFormat('en-US');
const compactFormat = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 });
const moneyFormat = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });
const centsFormat = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 2, maximumFractionDigits: 2 });
const languageNames = new Intl.DisplayNames(['en'], { type: 'language' });

export function formatNumber(value: number): string {
    return Math.abs(value) >= 100_000 ? compactFormat.format(value) : numberFormat.format(Math.round(value));
}

/** Whole dollars from $100 up, cents below it, where they still matter. */
export function formatMoney(value: number): string {
    return Math.abs(value) >= 100 ? moneyFormat.format(value) : centsFormat.format(value);
}

export function formatPercent(value: number, digits = 1): string {
    if (!Number.isFinite(value)) {
        return '—';
    }

    const rounded = value.toFixed(value !== 0 && Math.abs(value) < 10 ? digits : 0);

    return `${rounded.replace(/\.0$/, '')}%`;
}

/** share of whole, as a percentage; 0 when there is no whole. */
export function share(part: number, whole: number): number {
    return whole > 0 ? (part / whole) * 100 : 0;
}

export function parseDay(value: string): Date {
    return new Date(`${value}T00:00:00`);
}

export function formatDay(value: string, withYear = false): string {
    return parseDay(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', ...(withYear ? { year: 'numeric' } : {}) });
}

/** "Oct 21 – Nov 19", with years only when the range crosses one. */
export function formatRange(from: string, to: string): string {
    if (from === to) {
        return formatDay(from, true);
    }

    const crossesYear = from.slice(0, 4) !== to.slice(0, 4);

    return `${formatDay(from, crossesYear)} – ${formatDay(to, true)}`;
}

/** "en" -> "English"; null -> "Unknown". */
export function languageName(code: string | null): string {
    if (!code) {
        return 'Unknown';
    }

    try {
        return languageNames.of(code) ?? code;
    } catch {
        return code;
    }
}

export type Trend = { direction: 'up' | 'down' | 'flat' | 'new'; text: string };

/** Change from the previous period, in percent. */
export function trend(value: number, previous: number): Trend {
    if (previous === 0) {
        return value === 0 ? { direction: 'flat', text: '0%' } : { direction: 'new', text: 'new' };
    }

    const change = ((value - previous) / previous) * 100;

    if (Math.abs(change) < 0.05) {
        return { direction: 'flat', text: '0%' };
    }

    // Past +999% a multiple reads better: "×12" rather than "+1,100%".
    const text = change >= 1000 ? `×${Math.round(value / previous)}` : `${change > 0 ? '+' : '−'}${formatPercent(Math.abs(change))}`;

    return { direction: change > 0 ? 'up' : 'down', text };
}
