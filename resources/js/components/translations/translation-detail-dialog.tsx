import { formatDateTime, Pill, RatingMark } from '@/components/badges';
import { DetailField, DetailSection } from '@/components/data-table/detail-fields';
import { useEntityDetail } from '@/components/data-table/use-entity-detail';
import { ENGINE_LABELS, type TranslationRow, type TranslationUser } from '@/components/translations/columns';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { displayName } from '@/lib/people';

interface TranslationDetail {
    id: number;
    text: string | null;
    braille: string | null;
    image_url: string | null;
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

                        {translation.image_url && (
                            <a href={translation.image_url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg border border-border bg-muted">
                                <img
                                    src={translation.image_url}
                                    alt="The scanned page with the recognised cells marked"
                                    className="max-h-[360px] w-full object-contain"
                                    onError={(event) => {
                                        event.currentTarget.replaceWith(
                                            Object.assign(document.createElement('p'), {
                                                className: 'p-6 text-center text-sm text-muted-foreground',
                                                textContent: 'The photo could not be loaded.',
                                            }),
                                        );
                                    }}
                                />
                            </a>
                        )}

                        <div className="flex flex-col gap-2">
                            <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Text</h3>
                            <p className="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm whitespace-pre-wrap">
                                {translation.text?.trim() || <span className="text-muted-foreground">No text found</span>}
                            </p>
                            {translation.braille?.trim() && (
                                <p className="rounded-lg border border-border bg-muted/30 px-3 py-2 text-lg leading-relaxed break-all whitespace-pre-wrap">
                                    {translation.braille}
                                </p>
                            )}
                        </div>

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
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
