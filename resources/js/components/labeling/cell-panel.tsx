import { useEffect, useRef } from 'react';

import { cn } from '@/lib/utils';

import { brailleChar, dotNumbers, dotPosition, type Box } from './cells';

/**
 * The selected cell enlarged from the photo, so its dots can be checked
 * without zooming the whole page. Drawn from the already loaded <img>.
 */
export function Loupe({ photoUrl, box, width, height, pattern }: { photoUrl: string | null; box: Box; width: number; height: number; pattern: number }) {
    const canvas = useRef<HTMLCanvasElement>(null);
    const img = useRef<HTMLImageElement | null>(null);

    useEffect(() => {
        if (!photoUrl) return;
        const el = new Image();
        el.src = photoUrl;
        img.current = el;
        el.onload = () => draw();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [photoUrl]);

    const draw = () => {
        const c = canvas.current;
        const el = img.current;
        if (!c || !el || !el.complete || !el.naturalWidth) return;
        const ctx = c.getContext('2d')!;
        const fx = el.naturalWidth / width;
        const fy = el.naturalHeight / height;
        // The box plus half its size around it, the area the classifier sees and a bit more.
        const bw = box.x2 - box.x1;
        const bh = box.y2 - box.y1;
        const side = Math.max(bw, bh * (c.width / c.height)) * 2;
        const cx = (box.x1 + box.x2) / 2;
        const cy = (box.y1 + box.y2) / 2;
        const sw = side;
        const sh = side * (c.height / c.width);
        ctx.fillStyle = '#111';
        ctx.fillRect(0, 0, c.width, c.height);
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(el, (cx - sw / 2) * fx, (cy - sh / 2) * fy, sw * fx, sh * fy, 0, 0, c.width, c.height);
        const k = c.width / sw;
        const x = (box.x1 - (cx - sw / 2)) * k;
        const y = (box.y1 - (cy - sh / 2)) * k;
        ctx.strokeStyle = 'rgba(124, 58, 237, 0.9)';
        ctx.lineWidth = 1.5;
        ctx.strokeRect(x, y, bw * k, bh * k);
        for (let d = 0; d < 6; d++) {
            const { fx: px, fy: py } = dotPosition(d);
            ctx.beginPath();
            ctx.arc(x + bw * k * px, y + bh * k * py, Math.min(bw, bh) * k * 0.06, 0, Math.PI * 2);
            if (pattern & (1 << d)) {
                ctx.fillStyle = 'rgba(124, 58, 237, 0.85)';
                ctx.fill();
            } else {
                ctx.strokeStyle = 'rgba(124, 58, 237, 0.6)';
                ctx.lineWidth = 1;
                ctx.stroke();
            }
        }
    };

    useEffect(draw);

    return <canvas ref={canvas} width={240} height={240} className="aspect-square w-full rounded-md border border-border bg-neutral-900" />;
}

/** The six dots as big toggles, numbered as in braille (1 2 3 down the left, 4 5 6 down the right). */
export function DotPad({ pattern, onToggle, serverPattern }: { pattern: number; onToggle: (dot: number) => void; serverPattern?: number }) {
    return (
        <div className="flex items-center gap-4">
            <div className="grid grid-flow-col grid-cols-2 grid-rows-3 gap-1.5">
                {[0, 1, 2, 3, 4, 5].map((d) => {
                    const raised = (pattern & (1 << d)) !== 0;
                    const serverDiffers = serverPattern !== undefined && ((serverPattern >> d) & 1) !== ((pattern >> d) & 1);
                    return (
                        <button
                            key={d}
                            type="button"
                            onClick={() => onToggle(d)}
                            title={`Dot ${d + 1} (key ${d + 1})`}
                            className={cn(
                                'flex size-9 items-center justify-center rounded-full border text-xs font-medium tabular-nums transition-colors',
                                raised ? 'border-violet-600 bg-violet-600 text-white' : 'border-border text-muted-foreground hover:bg-muted',
                                serverDiffers && 'ring-2 ring-rose-500 ring-offset-1 ring-offset-background',
                            )}
                        >
                            {d + 1}
                        </button>
                    );
                })}
            </div>
            <div className="flex flex-col items-start gap-0.5">
                <span className="text-4xl leading-none">{brailleChar(pattern)}</span>
                <span className="text-xs text-muted-foreground">dots {dotNumbers(pattern)}</span>
            </div>
        </div>
    );
}
