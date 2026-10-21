/** The app's recognition languages and their names (BrailleRecognitionFlutter lib/language.dart). */
export const LANGUAGE_NAMES: Record<string, string> = {
    EN: 'English, grade 1',
    EN2: 'English, grade 2',
    PT: 'Portuguese',
    RU: 'Russian',
    UZ: 'Uzbek (Cyrillic)',
    UZL: 'Uzbek (Latin)',
    DE: 'German',
    GR: 'Greek',
    LV: 'Latvian',
    PL: 'Polish',
    FR: 'French',
    ES: 'Spanish',
    AR: 'Arabic',
};

export function languageName(code: string): string {
    return LANGUAGE_NAMES[code] ?? code;
}

/** Auto counts a page as detected when the best language beats the next by this much (braille_text confidentMargin). */
export const CONFIDENT_MARGIN = 5;
