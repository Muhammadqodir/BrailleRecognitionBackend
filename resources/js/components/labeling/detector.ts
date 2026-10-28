import { classify, loadOrt, session, type GrayPhoto } from './dot-classifier';

/**
 * The cell detector, run in the browser so a page can start from a newer
 * model than the one on the user's phone. The steps are the app's own
 * (BrailleRecognitionFlutter lib/ocr/pipeline.dart): letterbox to 1024 with
 * grey 114 padding, YOLO output -> boxes over 0.25, overlap NMS (over the
 * smaller box, 0.4), then the dot classifier; cells with no dots are dropped.
 * A lower [conf] finds more cells on faint pages (and more false ones).
 */
const DET_SIZE = 1024;
export const DET_CONF = 0.25;
const MAX_OVERLAP = 0.4;
const MAX_CELLS = 1500;

interface Found {
    x1: number;
    y1: number;
    x2: number;
    y2: number;
    score: number;
}

function overlap(a: Found, b: Found): number {
    const ix = Math.max(0, Math.min(a.x2, b.x2) - Math.max(a.x1, b.x1));
    const iy = Math.max(0, Math.min(a.y2, b.y2) - Math.max(a.y1, b.y1));
    const m = Math.min((a.x2 - a.x1) * (a.y2 - a.y1), (b.x2 - b.x1) * (b.y2 - b.y1));
    return m > 0 ? (ix * iy) / m : 0;
}

/**
 * Cells the models find in the photo, unturned, in its [width] x [height]
 * pixels: [x1, y1, x2, y2, pattern, detector score, dot margin], as the phone stores them.
 */
export async function detectCells(detectorUrl: string, classifierUrl: string, photoUrl: string, photo: GrayPhoto, conf = DET_CONF): Promise<number[][]> {
    const { width, height } = photo;
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.src = photoUrl;
    await img.decode();

    const r = DET_SIZE / Math.max(width, height);
    const nw = Math.round(width * r);
    const nh = Math.round(height * r);
    const left = Math.round((DET_SIZE - nw) / 2 - 0.1);
    const top = Math.round((DET_SIZE - nh) / 2 - 0.1);
    const canvas = document.createElement('canvas');
    canvas.width = DET_SIZE;
    canvas.height = DET_SIZE;
    const ctx = canvas.getContext('2d', { willReadFrequently: true })!;
    ctx.fillStyle = 'rgb(114, 114, 114)';
    ctx.fillRect(0, 0, DET_SIZE, DET_SIZE);
    ctx.drawImage(img, left, top, nw, nh);
    const rgba = ctx.getImageData(0, 0, DET_SIZE, DET_SIZE).data;
    const plane = DET_SIZE * DET_SIZE;
    const input = new Float32Array(plane * 3);
    for (let p = 0, j = 0; p < plane; p++, j += 4) {
        input[p] = rgba[j] / 255;
        input[plane + p] = rgba[j + 1] / 255;
        input[2 * plane + p] = rgba[j + 2] / 255;
    }

    const [ort, s] = await Promise.all([loadOrt(), session(detectorUrl)]);
    const res = await s.run({ [s.inputNames[0]]: new ort.Tensor('float32', input, [1, 3, DET_SIZE, DET_SIZE]) });
    const out = res[s.outputNames[0]].data as Float32Array;
    const n = out.length / 5;

    const cand: Found[] = [];
    for (let i = 0; i < n; i++) {
        const score = out[4 * n + i];
        if (score < conf) continue;
        const cx = out[i];
        const cy = out[n + i];
        const bw = out[2 * n + i];
        const bh = out[3 * n + i];
        cand.push({ x1: (cx - bw / 2 - left) / r, y1: (cy - bh / 2 - top) / r, x2: (cx + bw / 2 - left) / r, y2: (cy + bh / 2 - top) / r, score });
    }
    cand.sort((a, b) => b.score - a.score);
    const keep: Found[] = [];
    const suppressed = new Array<boolean>(cand.length).fill(false);
    for (let i = 0; i < cand.length && keep.length < MAX_CELLS; i++) {
        if (suppressed[i]) continue;
        keep.push(cand[i]);
        for (let j = i + 1; j < cand.length; j++) {
            if (!suppressed[j] && overlap(cand[i], cand[j]) > MAX_OVERLAP) suppressed[j] = true;
        }
    }

    const dots = await classify(classifierUrl, photo, keep);
    const round = (v: number) => Math.round(v * 10) / 10;
    return keep
        .map((c, k) => [round(c.x1), round(c.y1), round(c.x2), round(c.y2), dots[k].pattern, Math.round(c.score * 1000) / 1000, Math.round(dots[k].margin * 1000) / 1000])
        .filter((c) => c[4] > 0);
}
