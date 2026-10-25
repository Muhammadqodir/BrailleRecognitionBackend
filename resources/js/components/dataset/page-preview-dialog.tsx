import { LoaderCircle, PencilLine } from 'lucide-react';
import { useEffect, useState } from 'react';

import { dotPosition, turnedSize, turnMatrix } from '@/components/labeling/cells';
import { COLORS } from '@/components/labeling/editor';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

import type { DatasetRow } from './columns';

interface PreviewData {
    photo_url: string | null;
    width: number;
    height: number;
    rotation: number;
    model_cells: number[][];
    cells: number[][] | null;
}

/**
 * A page's photo with its cells drawn on it: the labeled cells once it is
 * labeled, the phone's own before. Loaded from the labeling page's JSON.
 */
export function PagePreviewDialog({ row, pageUrl, onClose, onEdit }: { row: DatasetRow | null; pageUrl: (id: number) => string; onClose: () => void; onEdit: (row: DatasetRow) => void }) {
    const [data, setData] = useState<PreviewData | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [showDots, setShowDots] = useState(true);

    useEffect(() => {
        if (!row) return;
        let cancelled = false;
        setData(null);
        setError(null);
        fetch(pageUrl(row.id), { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error(r.status === 401 ? 'You were signed out.' : `Request failed with ${r.status}`))))
            .then(({ data }: { data: PreviewData }) => !cancelled && setData(data))
            .catch((e: Error) => !cancelled && setError(e.message));
        return () => {
            cancelled = true;
        };
    }, [row, pageUrl]);

    const labeled = Boolean(data?.cells);
    // Saved cells are in the photo as it was turned for labeling; the phone's are in the photo as stored.
    const rotation = labeled ? (data?.rotation ?? 0) : 0;
    const cells = data ? (data.cells ?? data.model_cells) : [];
    const frame = data ? turnedSize(data.width, data.height, rotation) : { w: 1, h: 1 };
    const color = labeled ? COLORS.human : COLORS.model;
    const stroke = Math.max(frame.w, frame.h) / 600;

    return (
        <Dialog open={row !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="flex max-h-[92vh] flex-col gap-3 sm:max-w-5xl">
                <DialogHeader>
                    <DialogTitle>
                        Page #{row?.id} · scan {row?.translation_id}
                    </DialogTitle>
                    <DialogDescription>
                        {data
                            ? `${cells.length} ${labeled ? 'labeled cells' : 'cells the phone found (not labeled yet)'}${rotation ? ` · turned ${rotation}°` : ''}`
                            : 'Loading…'}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex min-h-0 flex-1 items-center justify-center overflow-auto rounded-md bg-neutral-900">
                    {error ? (
                        <p className="p-6 text-sm text-destructive">{error}</p>
                    ) : !data ? (
                        <LoaderCircle className="m-12 size-5 animate-spin text-muted-foreground" />
                    ) : data.photo_url ? (
                        <svg viewBox={`0 0 ${frame.w} ${frame.h}`} className="max-h-[72vh] w-auto max-w-full" style={{ aspectRatio: `${frame.w} / ${frame.h}` }}>
                            <image
                                href={data.photo_url}
                                width={data.width}
                                height={data.height}
                                preserveAspectRatio="none"
                                transform={`matrix(${turnMatrix(rotation, data.width, data.height).join(' ')})`}
                            />
                            {cells.map((c, i) => {
                                const w = c[2] - c[0];
                                const h = c[3] - c[1];
                                return (
                                    <g key={i}>
                                        <rect x={c[0]} y={c[1]} width={w} height={h} fill="none" stroke={color} strokeWidth={stroke} />
                                        {showDots &&
                                            [0, 1, 2, 3, 4, 5]
                                                .filter((d) => c[4] & (1 << d))
                                                .map((d) => {
                                                    const { fx, fy } = dotPosition(d);
                                                    return <circle key={d} cx={c[0] + w * fx} cy={c[1] + h * fy} r={Math.min(w, h) * 0.08} fill={color} fillOpacity={0.85} />;
                                                })}
                                    </g>
                                );
                            })}
                        </svg>
                    ) : (
                        <p className="p-6 text-sm text-muted-foreground">No photo</p>
                    )}
                </div>

                <div className="flex items-center justify-between gap-2">
                    <Button variant="ghost" size="sm" onClick={() => setShowDots((v) => !v)} disabled={!data}>
                        {showDots ? 'Hide dots' : 'Show dots'}
                    </Button>
                    {row && (
                        <Button size="sm" onClick={() => onEdit(row)}>
                            <PencilLine />
                            Open in editor
                        </Button>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
