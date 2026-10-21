import { LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { CONFIDENT_MARGIN, languageName } from '@/lib/languages';

interface Reading {
    text: string | null;
    braille: string | null;
}

interface ReadAsTextProps {
    readUrl: string;
    storedLang: string;
    stored: Reading;
    /** Languages the scan can be read in again; empty when it has no stored cells. */
    readableLangs: string[];
    langScores: { lang: string; score: number }[];
    detectMargin: number | null;
}

/**
 * The scan's text, with a "Read as" picker that reads the same cells in another
 * language on the server, as the app does when someone changes the language on
 * a result. Nothing is saved, so it is safe for checking what Auto picked.
 */
export function ReadAsText({ readUrl, storedLang, stored, readableLangs, langScores, detectMargin }: ReadAsTextProps) {
    const [lang, setLang] = useState(storedLang);
    const [readings, setReadings] = useState<Record<string, Reading>>({ [storedLang]: stored });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (readings[lang]) {
            return;
        }

        let cancelled = false;
        setLoading(true);
        setError(null);

        fetch(`${readUrl}?lang=${encodeURIComponent(lang)}`, { headers: { Accept: 'application/json' } })
            .then(async (response) => {
                const json = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(json.message ?? `Request failed with ${response.status}`);
                }
                if (!cancelled) {
                    setReadings((current) => ({ ...current, [lang]: json.data }));
                }
            })
            .catch((e: Error) => !cancelled && setError(e.message))
            .finally(() => !cancelled && setLoading(false));

        return () => {
            cancelled = true;
        };
    }, [lang, readUrl, readings]);

    const reading = readings[lang];
    const best = langScores[0]?.lang ?? null;
    const options = readableLangs.includes(storedLang) || readableLangs.length === 0 ? readableLangs : [storedLang, ...readableLangs];

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between gap-3">
                <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Text</h3>
                {readableLangs.length > 0 ? (
                    <div className="flex items-center gap-2">
                        <span className="text-xs text-muted-foreground">Read as</span>
                        <Select value={lang} onValueChange={setLang}>
                            <SelectTrigger size="sm" className="h-8 w-56 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.map((code) => (
                                    <SelectItem key={code} value={code} className="text-xs">
                                        <span className="w-9 font-mono">{code}</span>
                                        <span>{languageName(code)}</span>
                                        {code === storedLang && <span className="ml-1 text-muted-foreground">· saved</span>}
                                        {code === best && code !== storedLang && <span className="ml-1 text-muted-foreground">· Auto's pick</span>}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                ) : (
                    <span className="text-xs text-muted-foreground">Read by the OCR server: no cells kept to read it in another language</span>
                )}
            </div>

            <div className={cn('flex flex-col gap-2 transition-opacity', loading && 'opacity-50')}>
                {error ? (
                    <p className="rounded-lg border border-destructive/40 px-3 py-2 text-sm text-destructive">{error}</p>
                ) : (
                    <>
                        <p className="relative rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm whitespace-pre-wrap">
                            {loading && !reading && <LoaderCircle className="mr-2 inline size-4 animate-spin" />}
                            {reading ? reading.text?.trim() || <span className="text-muted-foreground">No text found</span> : loading ? 'Reading…' : null}
                        </p>
                        {reading?.braille?.trim() && (
                            <p className="rounded-lg border border-border bg-muted/30 px-3 py-2 text-lg leading-relaxed break-all whitespace-pre-wrap">
                                {reading.braille}
                            </p>
                        )}
                    </>
                )}
                {lang !== storedLang && (
                    <p className="text-xs text-muted-foreground">
                        Preview in {languageName(lang)}, not saved. The scan is saved as {languageName(storedLang)}.
                    </p>
                )}
            </div>

            {langScores.length > 0 && (
                <div className="flex flex-col gap-2 pt-1">
                    <div className="flex items-baseline justify-between gap-3 text-xs text-muted-foreground">
                        <span className="font-semibold tracking-wide uppercase">Auto-detect scores</span>
                        {detectMargin !== null && (
                            <span>
                                {best} won by {detectMargin} · {detectMargin >= CONFIDENT_MARGIN ? 'confident' : `not confident (under ${CONFIDENT_MARGIN})`}
                            </span>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-1.5">
                        {langScores.map((entry) => (
                            <button key={entry.lang} type="button" onClick={() => readableLangs.includes(entry.lang) && setLang(entry.lang)}>
                                <Badge
                                    variant={entry.lang === lang ? 'default' : 'outline'}
                                    className={cn('gap-1.5 font-normal tabular-nums', entry.lang === best && entry.lang !== lang && 'border-foreground/40')}
                                >
                                    <span className="font-mono font-medium">{entry.lang}</span>
                                    {entry.score}
                                </Badge>
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
