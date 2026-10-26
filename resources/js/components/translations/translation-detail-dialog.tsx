import { formatDateTime, Pill, RatingMark } from '@/components/badges';
import { DetailField, DetailSection } from '@/components/data-table/detail-fields';
import { useEntityDetail } from '@/components/data-table/use-entity-detail';
import { ENGINE_LABELS, type TranslationRow, type TranslationUser } from '@/components/translations/columns';
import { ReadAsText } from '@/components/translations/read-as-text';
import { MarkedScan, ScanImage, type Cell } from '@/components/translations/scan-images';
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
    app_version: string | null;
    size: string | null;
    timings: Record<string, number>;
    detected_lang: string | null;
    lang_source: string | null;
    lang_scores: { lang: string; score: number }[];
    detect_margin: number | null;
    readable_langs: string[];
    turn: number | null;
}

interface TranslationDetailDialogProps {
    translationId: number | null;
    detailUrl: string | null;
    onOpenChange: (open: boolean) => void;
    onUserClick: (userId: number) => void;
}

/** prepare_ms -> Prepare */
function timingLabel(key: string): string {
    const words = key.replace(/_ms$/, '').replace(/_/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

export function TranslationDetailDialog({ translationId, detailUrl, onOpenChange, onUserClick }: TranslationDetailDialogProps) {
    const { data: translation, loading } = useEntityDetail<TranslationDetail>(detailUrl);
    const timings = Object.entries(translation?.timings ?? {});

    return (
        <Dialog open={translationId !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] w-full max-w-2xl overflow-y-auto sm:max-w-2xl">
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
                            </div>
                        </DialogHeader>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <ScanImage label="Photo" url={translation.photo_url} />
                            {translation.engine === 'device' ? (
                                <MarkedScan url={translation.photo_url} cells={translation.cells} width={translation.width} height={translation.height} />
                            ) : (
                                <ScanImage label="Marked by the OCR server" url={translation.marked_url} />
                            )}
                        </div>

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
                            <DetailField label="App version" value={translation.app_version} />
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
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
