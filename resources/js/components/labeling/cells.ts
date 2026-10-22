/** Box geometry and bookkeeping for the labeling page. Boxes are in photo pixels. */

export interface Box {
    x1: number;
    y1: number;
    x2: number;
    y2: number;
}

export type Origin = 'model' | 'server' | 'human';

export interface LabelCell extends Box {
    id: number;
    /** Bit d is dot d + 1; 0 until the dots are known. */
    pattern: number;
    origin: Origin;
    /** The phone detector's score and its classifier's smallest |logit|, for its own cells. */
    score?: number;
    margin?: number;
    /** The OCR host's dots for the same cell, when it read them differently. */
    serverPattern?: number;
    /** Dots set by hand: moving the box no longer re-reads them with the model. */
    dotsByHand?: boolean;
    /** Box moved or resized by hand. */
    moved?: boolean;
}

/** [x1, y1, x2, y2, pattern, score] from the OCR host. */
export type Suggestion = [number, number, number, number, number, number];

export const LOW_SCORE = 0.45;
export const LOW_MARGIN = 1.0;
export const MATCH_IOU = 0.3;

export function iou(a: Box, b: Box): number {
    const w = Math.min(a.x2, b.x2) - Math.max(a.x1, b.x1);
    const h = Math.min(a.y2, b.y2) - Math.max(a.y1, b.y1);
    if (w <= 0 || h <= 0) return 0;
    const inter = w * h;
    return inter / ((a.x2 - a.x1) * (a.y2 - a.y1) + (b.x2 - b.x1) * (b.y2 - b.y1) - inter);
}

export function boxOf(c: number[]): Box {
    return { x1: c[0], y1: c[1], x2: c[2], y2: c[3] };
}

/** Greedy one-to-one matching by IoU, best pairs first: pairs of indexes into a and b. */
export function match(a: Box[], b: Box[], minIou = MATCH_IOU): [number, number][] {
    const candidates: [number, number, number][] = [];
    a.forEach((ca, i) =>
        b.forEach((cb, j) => {
            if (cb.x1 >= ca.x2 || cb.x2 <= ca.x1 || cb.y1 >= ca.y2 || cb.y2 <= ca.y1) return;
            const v = iou(ca, cb);
            if (v >= minIou) candidates.push([v, i, j]);
        }),
    );
    candidates.sort((x, y) => y[0] - x[0]);
    const usedA = new Set<number>();
    const usedB = new Set<number>();
    const pairs: [number, number][] = [];
    for (const [, i, j] of candidates) {
        if (usedA.has(i) || usedB.has(j)) continue;
        usedA.add(i);
        usedB.add(j);
        pairs.push([i, j]);
    }
    return pairs;
}

export function isUnsure(c: LabelCell): boolean {
    if (c.origin !== 'model' || c.dotsByHand) return false;
    return (c.score ?? 1) < LOW_SCORE || (c.margin ?? 9) < LOW_MARGIN || c.serverPattern !== undefined;
}

function median(values: number[]): number {
    if (values.length === 0) return 0;
    const s = [...values].sort((a, b) => a - b);
    return s[Math.floor(s.length / 2)];
}

/** The page's typical cell size, for boxes added with one click. */
export function typicalSize(cells: Box[], width: number): { w: number; h: number } {
    if (cells.length === 0) return { w: width * 0.025, h: width * 0.038 };
    return { w: median(cells.map((c) => c.x2 - c.x1)), h: median(cells.map((c) => c.y2 - c.y1)) };
}

/** Cells in reading order: rows top to bottom, each left to right. */
export function readingOrder(cells: LabelCell[]): LabelCell[] {
    if (cells.length === 0) return [];
    const h = typicalSize(cells, 1).h;
    const byY = [...cells].sort((a, b) => a.y1 + a.y2 - b.y1 - b.y2);
    const rows: LabelCell[][] = [];
    let rowY = -Infinity;
    for (const c of byY) {
        const cy = (c.y1 + c.y2) / 2;
        if (cy - rowY > h * 0.5) {
            rows.push([]);
            rowY = cy;
        }
        rows[rows.length - 1].push(c);
    }
    return rows.flatMap((row) => row.sort((a, b) => a.x1 - b.x1));
}

/** The Unicode braille character for a pattern (bit d = dot d + 1). */
export function brailleChar(pattern: number): string {
    return String.fromCodePoint(0x2800 + (pattern & 63));
}

/** Raised dot numbers, e.g. "1 2 5". */
export function dotNumbers(pattern: number): string {
    const dots = [0, 1, 2, 3, 4, 5].filter((d) => pattern & (1 << d)).map((d) => d + 1);
    return dots.length ? dots.join(' ') : 'none';
}

/** Where dot d sits in its box, as fractions of the box (the app's own layout). */
export function dotPosition(d: number): { fx: number; fy: number } {
    return { fx: 0.32 + 0.36 * Math.floor(d / 3), fy: 0.22 + 0.28 * (d % 3) };
}

/** What the admin changed against the phone's cells, saved with the page. */
export function editStats(initial: LabelCell[], final: LabelCell[]): Record<string, number> {
    const model = initial.filter((c) => c.origin === 'model');
    const pairs = match(model, final, 0.5);
    const kept = new Set(pairs.map(([, j]) => j));
    return {
        model_cells: model.length,
        cells: final.length,
        deleted: model.length - pairs.length,
        added: final.length - kept.size,
        from_server: final.filter((c, j) => !kept.has(j) && c.origin === 'server').length,
        dots_changed: pairs.filter(([i, j]) => model[i].pattern !== final[j].pattern).length,
        moved: pairs.filter(([i, j]) => iou(model[i], final[j]) < 0.9).length,
    };
}
