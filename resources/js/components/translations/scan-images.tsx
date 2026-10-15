import { ImageOff } from 'lucide-react';
import { useState, type ReactNode } from 'react';

/** The app's primary colour, which it marks cells with. */
const MARK_COLOR = '#009AD1';

/** [x1, y1, x2, y2, dot pattern] in photo pixels; bit d of the pattern is dot d+1. */
export type Cell = [number, number, number, number, number];

function Frame({ label, href, children }: { label: string; href?: string | null; children: ReactNode }) {
    return (
        <figure className="flex min-w-0 flex-col gap-1.5">
            <figcaption className="flex items-center justify-between text-xs text-muted-foreground">
                {label}
                {href && (
                    <a href={href} target="_blank" rel="noreferrer" className="hover:text-foreground hover:underline">
                        Open
                    </a>
                )}
            </figcaption>
            <div className="flex h-[300px] items-center justify-center overflow-hidden rounded-lg border border-border bg-muted">{children}</div>
        </figure>
    );
}

function Missing({ text }: { text: string }) {
    return (
        <div className="flex flex-col items-center gap-2 p-6 text-center text-sm text-muted-foreground">
            <ImageOff className="size-5" />
            {text}
        </div>
    );
}

/** A picture as stored; a placeholder when there is none or it does not load. */
export function ScanImage({ label, url, missing = 'No picture stored' }: { label: string; url: string | null; missing?: string }) {
    const [failed, setFailed] = useState(false);

    return (
        <Frame label={label} href={url && !failed ? url : null}>
            {url && !failed ? (
                <img src={url} alt={label} className="max-h-full max-w-full object-contain" onError={() => setFailed(true)} />
            ) : (
                <Missing text={url ? 'The picture could not be loaded' : missing} />
            )}
        </Frame>
    );
}

/**
 * The photo with every recognised cell drawn over it, as the app shows a scan
 * read on the phone: a light rounded box per cell with its six dots, raised
 * ones solid. The boxes are in photo pixels, so the overlay shares the photo's
 * own coordinate system.
 */
export function MarkedScan({ url, cells, width, height }: { url: string | null; cells: Cell[]; width: number | null; height: number | null }) {
    const [failed, setFailed] = useState(false);
    const [natural, setNatural] = useState<{ w: number; h: number } | null>(null);
    const w = width ?? natural?.w ?? 0;
    const h = height ?? natural?.h ?? 0;

    return (
        <Frame label={`Marked · ${cells.length} cell${cells.length === 1 ? '' : 's'}`}>
            {url && !failed ? (
                <div className="relative">
                    <img
                        src={url}
                        alt="The scan with the recognised cells marked"
                        className="block max-h-[298px] max-w-full"
                        onError={() => setFailed(true)}
                        onLoad={(event) => setNatural({ w: event.currentTarget.naturalWidth, h: event.currentTarget.naturalHeight })}
                    />
                    {w > 0 && h > 0 && cells.length > 0 && (
                        <svg viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" className="absolute inset-0 size-full" aria-hidden>
                            <rect width={w} height={h} fill="#0B1A24" opacity={0.2} />
                            {cells.map(([x1, y1, x2, y2, pattern], index) => {
                                const cw = x2 - x1;
                                const ch = y2 - y1;
                                const r = Math.min(cw, ch);

                                return (
                                    <g key={index}>
                                        <rect
                                            x={x1}
                                            y={y1}
                                            width={cw}
                                            height={ch}
                                            rx={r * 0.2}
                                            fill="white"
                                            fillOpacity={0.92}
                                            stroke={MARK_COLOR}
                                            strokeOpacity={0.8}
                                            strokeWidth={Math.max(1, r * 0.04)}
                                        />
                                        {[0, 1, 2, 3, 4, 5].map((dot) => {
                                            const raised = (pattern & (1 << dot)) !== 0;

                                            return (
                                                <circle
                                                    key={dot}
                                                    cx={x1 + cw * (0.32 + 0.36 * Math.floor(dot / 3))}
                                                    cy={y1 + ch * (0.22 + 0.28 * (dot % 3))}
                                                    r={r * (raised ? 0.09 : 0.054)}
                                                    fill={MARK_COLOR}
                                                    fillOpacity={raised ? 1 : 0.15}
                                                />
                                            );
                                        })}
                                    </g>
                                );
                            })}
                        </svg>
                    )}
                </div>
            ) : (
                <Missing text={url ? 'The picture could not be loaded' : 'No picture stored'} />
            )}
        </Frame>
    );
}
