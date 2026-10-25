import { turnedSize, turnMatrix, type Box } from './cells';

/**
 * The app's dot classifier, run in the browser so a box drawn or moved by hand
 * gets its dots read the way the phone would read them. The crop is the app's
 * own (BrailleRecognitionFlutter lib/ocr/pipeline.dart _crop): the box plus
 * 20% margin, resampled bilinearly to 48x32 grayscale with edge replication.
 */
const CLS_H = 48;
const CLS_W = 32;
const CROP_MARGIN = 0.2;

type Ort = typeof import('onnxruntime-web/wasm');
type Session = import('onnxruntime-web/wasm').InferenceSession;

let ortPromise: Promise<Ort> | null = null;
const sessions = new Map<string, Promise<Session>>();

export function loadOrt(): Promise<Ort> {
    // The CPU-only build (no WebGPU), loaded on first use; one thread is plenty for a few crops.
    ortPromise ??= import('onnxruntime-web/wasm').then((ort) => {
        ort.env.wasm.numThreads = 1;
        return ort;
    });
    return ortPromise;
}

export function session(url: string): Promise<Session> {
    let s = sessions.get(url);
    if (!s) {
        s = loadOrt().then((ort) => ort.InferenceSession.create(url, { executionProviders: ['wasm'] }));
        s.catch(() => sessions.delete(url));
        sessions.set(url, s);
    }
    return s;
}

/**
 * The photo turned clockwise by [rotation], as a local URL the page shows and
 * reads like the photo itself ([width] x [height] is the unturned size the cells use).
 */
export async function turnedPhoto(url: string, width: number, height: number, rotation: number): Promise<string> {
    if (rotation === 0) return url;
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.src = url;
    await img.decode();
    const { w, h } = turnedSize(width, height, rotation);
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d')!;
    ctx.setTransform(...turnMatrix(rotation, width, height));
    ctx.drawImage(img, 0, 0, width, height);
    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.95));
    if (!blob) throw new Error('The photo could not be turned');
    return URL.createObjectURL(blob);
}

/** The photo as grayscale in the cells' coordinate system (OpenCV's BGR2GRAY weights). */
export interface GrayPhoto {
    gray: Uint8Array;
    width: number;
    height: number;
}

export async function grayPhoto(url: string, width: number, height: number): Promise<GrayPhoto> {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.src = url;
    await img.decode();
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d', { willReadFrequently: true })!;
    ctx.drawImage(img, 0, 0, width, height);
    const rgba = ctx.getImageData(0, 0, width, height).data;
    const gray = new Uint8Array(width * height);
    for (let i = 0, j = 0; i < gray.length; i++, j += 4) {
        gray[i] = Math.floor((299 * rgba[j] + 587 * rgba[j + 1] + 114 * rgba[j + 2] + 500) / 1000);
    }
    return { gray, width, height };
}

function crop({ gray: g, width: w, height: h }: GrayPhoto, c: Box, dst: Float32Array, off: number) {
    const hw = (c.x2 - c.x1) * (0.5 + CROP_MARGIN);
    const hh = (c.y2 - c.y1) * (0.5 + CROP_MARGIN);
    const x0 = (c.x1 + c.x2) / 2 - hw;
    const y0 = (c.y1 + c.y2) / 2 - hh;
    const sx = (2 * hw) / CLS_W;
    const sy = (2 * hh) / CLS_H;
    for (let v = 0; v < CLS_H; v++) {
        const y = Math.min(Math.max(y0 + v * sy, 0), h - 1);
        const yi = Math.floor(y);
        const yj = Math.min(yi + 1, h - 1);
        const fy = y - yi;
        for (let u = 0; u < CLS_W; u++) {
            const x = Math.min(Math.max(x0 + u * sx, 0), w - 1);
            const xi = Math.floor(x);
            const xj = Math.min(xi + 1, w - 1);
            const fx = x - xi;
            const top = g[yi * w + xi] * (1 - fx) + g[yi * w + xj] * fx;
            const bot = g[yj * w + xi] * (1 - fx) + g[yj * w + xj] * fx;
            dst[off + v * CLS_W + u] = (top * (1 - fy) + bot * fy) / 255;
        }
    }
}

/** Dots for each box: the pattern (bit d = dot d + 1) and the smallest |logit|, how sure the model is. */
export async function classify(modelUrl: string, photo: GrayPhoto, boxes: Box[]): Promise<{ pattern: number; margin: number }[]> {
    if (boxes.length === 0) return [];
    const [ort, s] = await Promise.all([loadOrt(), session(modelUrl)]);
    const len = CLS_H * CLS_W;
    const batch = new Float32Array(boxes.length * len);
    boxes.forEach((b, k) => crop(photo, b, batch, k * len));
    const out = await s.run({ [s.inputNames[0]]: new ort.Tensor('float32', batch, [boxes.length, 1, CLS_H, CLS_W]) });
    const logits = out[s.outputNames[0]].data as Float32Array;
    return boxes.map((_, k) => {
        let pattern = 0;
        let margin = Infinity;
        for (let d = 0; d < 6; d++) {
            const l = logits[k * 6 + d];
            if (l > 0) pattern |= 1 << d;
            margin = Math.min(margin, Math.abs(l));
        }
        return { pattern, margin };
    });
}

/**
 * Centres a box on its dots: tries small shifts (up to a quarter of the box
 * across, a sixth down, well short of the next cell) and keeps the one the
 * model reads most confidently, by the summed |logit| of its six dots.
 */
export async function snap(modelUrl: string, photo: GrayPhoto, box: Box): Promise<{ box: Box; pattern: number; margin: number }> {
    const w = box.x2 - box.x1;
    const h = box.y2 - box.y1;
    const shifts: Box[] = [];
    for (let i = -3; i <= 3; i++) {
        for (let j = -3; j <= 3; j++) {
            const dx = (i / 3) * 0.25 * w;
            const dy = (j / 3) * 0.17 * h;
            shifts.push({ x1: box.x1 + dx, y1: box.y1 + dy, x2: box.x2 + dx, y2: box.y2 + dy });
        }
    }
    const [ort, s] = await Promise.all([loadOrt(), session(modelUrl)]);
    const len = CLS_H * CLS_W;
    const batch = new Float32Array(shifts.length * len);
    shifts.forEach((b, k) => crop(photo, b, batch, k * len));
    const out = await s.run({ [s.inputNames[0]]: new ort.Tensor('float32', batch, [shifts.length, 1, CLS_H, CLS_W]) });
    const logits = out[s.outputNames[0]].data as Float32Array;
    let best = Math.floor(shifts.length / 2);
    let bestScore = -Infinity;
    shifts.forEach((_, k) => {
        let sum = 0;
        let any = false;
        for (let d = 0; d < 6; d++) {
            sum += Math.abs(logits[k * 6 + d]);
            any ||= logits[k * 6 + d] > 0;
        }
        // Prefer the centre on ties, and never a spot with no dots at all.
        const score = any ? sum - 0.01 * Math.hypot((k % 7) - 3, Math.floor(k / 7) - 3) : -Infinity;
        if (score > bestScore) {
            bestScore = score;
            best = k;
        }
    });
    let pattern = 0;
    let margin = Infinity;
    for (let d = 0; d < 6; d++) {
        const l = logits[best * 6 + d];
        if (l > 0) pattern |= 1 << d;
        margin = Math.min(margin, Math.abs(l));
    }
    return { box: shifts[best], pattern, margin };
}
