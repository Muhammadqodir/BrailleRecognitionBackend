import { ArrowUpRight, SlidersHorizontal } from 'lucide-react';

import { formatDateTime, Pill, RatingMark } from '@/components/badges';
import { DetailField, DetailSection } from '@/components/data-table/detail-fields';
import { useEntityDetail } from '@/components/data-table/use-entity-detail';
import { ENGINE_LABELS, TUNE_HINT, type TranslationRow, type TranslationUser } from '@/components/translations/columns';
import { ReadAsText } from '@/components/translations/read-as-text';
import { MarkedScan, ScanImage, type Cell } from '@/components/translations/scan-images';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { displayName } from '@/lib/people';

interface TranslationDetail {
    id: number;
    text: string | null;
    braille: string | null;
    image_url: string | null;
    photo_url: string | null;
    marked_url: string | null;
    width: number | null;
    height: number | null;
    cells: Cell[];
    lang: string;
    rating: number | null;
    is_fav: boolean;
    legacy_id: number | null;
    user: TranslationUser | null;
    created_at: string;
    engine: TranslationRow['engine'];
    backend: string | null;
    model: string | null;
    size: string | null;
    timings: Record<string, number>;
    detected_lang: string | null;
    lang_source: string | null;
    lang_scores: { lang: string; score: number }[];
    detect_margin: number | null;
    readable_langs: string[];
    turn: number | null;
    label_status: TranslationRow['label_status'];
    can_tune: boolean;
}

interface TranslationDetailDialogProps {
    translationId: number | null;
    detailUrl: string | null;
    onOpenChange: (open: boolean) => void;
    onUserClick: (userId: number) => void;
    /** Tune was opened in another tab; the list may want to refresh when you come back. */
    onTuneOpened?: () => void;
}

/** prepare_ms -> Prepare */
function timingLabel(key: string): string {
    const words = key.replace(/_ms$/, '').replace(/_/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

export function TranslationDetailDialog({ translationId, detailUrl, onOpenChange, onUserClick, onTuneOpened }: TranslationDetailDialogProps) {
    const { data: translation, loading } = useEntityDetail<TranslationDetail>(detailUrl);
    const timings = Object.entries(translation?.timings ?? {});

    return (
        <Dialog open={translationId !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] w-full max-w-[calc(100%-2rem)] overflow-x-hidden overflow-y-auto sm:max-w-[min(72rem,calc(100%-4rem))] lg:p-6">
                {loading || !translation ? (
                    <div className="flex flex-col gap-4">
                        <Skeleton className="h-5 w-48" />
                        <Skeleton className="h-64 w-full" />
                        <Skeleton className="h-24 w-full" />
                    </div>
                ) : (
                    <>
                        <DialogHeader>
                            <DialogTitle>Translation #{translation.id}</DialogTitle>
                            <div className="flex flex-wrap items-center gap-1.5">
                                <Pill>{translation.lang}</Pill>
                                <Pill>{ENGINE_LABELS[translation.engine]}</Pill>
                                {translation.is_fav && <Pill>favourite</Pill>}
                                <RatingMark rating={translation.rating} />
                                <span className="text-xs text-muted-foreground">{formatDateTime(translation.created_at)}</span>
                                {translation.label_status === 'done' && <Pill>in dataset</Pill>}
                                {translation.can_tune && (
                                    <Button variant="outline" size="sm" className="mr-8 ml-auto" asChild>
                                        <a href={`${detailUrl}/tune`} target="_blank" rel="noopener" title={`${TUNE_HINT} Opens in a new tab.`} onClick={onTuneOpened}>
                                            <SlidersHorizontal />
                                            Tune
                                            <ArrowUpRight className="opacity-60" />
                                        </a>
                                    </Button>
                                )}
                            </div>
                        </DialogHeader>

                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
                            <div className="grid min-w-0 grid-cols-1 content-start gap-3 sm:grid-cols-2">
                                <ScanImage label="Photo" url={translation.photo_url} />
                                {translation.engine === 'device' ? (
                                    <MarkedScan url={translation.photo_url} cells={translation.cells} width={translation.width} height={translation.height} />
                                ) : (
                                    <ScanImage label="Marked by the OCR server" url={translation.marked_url} />
                                )}
                            </div>

                            <div className="flex min-w-0 flex-col gap-5">
                                <ReadAsText
                                    key={translation.id}
                                    readUrl={`${detailUrl}/read`}
                                    storedLang={translation.lang}
                                    stored={{ text: translation.text, braille: translation.braille }}
                                    readableLangs={translation.readable_langs}
                                    langScores={translation.lang_scores}
                                    detectMargin={translation.detect_margin}
                                />

                                <Separator />

                                <DetailSection title="Recognition">
                                    <DetailField label="Read by" value={ENGINE_LABELS[translation.engine]} />
                                    <DetailField label="Backend" value={translation.backend} />
                                    <DetailField label="Photo size" value={translation.size} />
                                    <DetailField label="Model" value={translation.model} className="col-span-2 sm:col-span-3" />
                                    <DetailField label="Detected language" value={translation.detected_lang} />
                                    <DetailField label="Language from" value={translation.lang_source} />
                                    <DetailField label="Rotated" value={translation.turn === null ? null : `${translation.turn}°`} />
                                    {timings.map(([key, value]) => (
                                        <DetailField key={key} label={timingLabel(key)} value={`${value} ms`} />
                                    ))}
                                </DetailSection>

                                <Separator />

                                <DetailSection title="User">
                                    <DetailField
                                        label="Account"
                                        value={
                                            translation.user && (
                                                <button type="button" className="hover:underline" onClick={() => translation.user && onUserClick(translation.user.id)}>
                                                    {displayName(translation.user)}
                                                </button>
                                            )
                                        }
                                    />
                                    <DetailField label="Email" value={translation.user?.email} className="col-span-2" />
                                    <DetailField label="Legacy ID" value={translation.legacy_id} />
                                </DetailSection>
                            </div>
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
