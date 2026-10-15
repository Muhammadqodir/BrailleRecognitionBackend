const regionNames = new Intl.DisplayNames(['en'], { type: 'region' });

export function countryName(code: string): string {
    try {
        return regionNames.of(code) ?? code;
    } catch {
        return code;
    }
}

/** The flag emoji for an ISO 3166 alpha-2 code, from its two regional indicator letters. */
export function countryFlag(code: string): string {
    return /^[A-Z]{2}$/.test(code) ? String.fromCodePoint(...[...code].map((letter) => 0x1f1e6 + letter.charCodeAt(0) - 65)) : '';
}

/** Facet options for a country filter, with the users that have no known country last. */
export function countryOptions(counts: Record<string, number>): { label: string; value: string; count?: number }[] {
    return [
        ...Object.entries(counts).map(([code, count]) => ({ label: `${countryFlag(code)} ${countryName(code)}`, value: code, count })),
        { label: 'Unknown', value: 'unknown' },
    ];
}
