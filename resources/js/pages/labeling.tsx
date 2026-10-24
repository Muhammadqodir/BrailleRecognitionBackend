import { Head, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, ChevronDown, Eye, EyeOff, Keyboard, LoaderCircle, Maximize, Redo2, Sparkles, Trash2, Undo2, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';

import { DotPad, Loupe } from '@/components/labeling/cell-panel';
import { boxOf, editStats, isChecked, isUnsure, match, readingOrder, typicalSize, type Box, type LabelCell, type Suggestion } from '@/components/labeling/cells';
import { classify, grayPhoto, snap, type GrayPhoto } from '@/components/labeling/dot-classifier';
import { COLORS, Editor, type EditorHandle } from '@/components/labeling/editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import { send, SignedOut } from '@/lib/http';
import { languageName } from '@/lib/languages';
import type { SharedProps } from '@/types';

interface SplitStats {
    todo: number;
    done: number;
    skipped: number;
}

interface Stats {
    train: SplitStats;
    test: SplitStats;
    cells: number;
    seconds: number;
    today: number;
}

interface LabelingProps {
    startId: number | null;
    stats: Stats;
    skipReasons: string[];
    tagNames: string[];
    langs: string[];
    classifierUrl: string | null;
    readerInstalled: boolean;
}

interface PageData {
    id: number;
    translation_id: number;
    photo_url: string | null;
    width: number;
    height: number;
    split: 'train' | 'test';
    status: 'todo' | 'done' | 'skipped';
    skip_reason: string | null;
    tags: string[];
    priority: number;
    issues: Record<string, number> | null;
    model_cells: number[][];
    suggestions: Suggestion[];
    cells: number[][] | null;
    lang: string;
    text: string | null;
    labeled_at: string | null;
    labeled_by: string | null;
    seconds: number;
    prev_id: number | null;
}

/** Server cells below this are too unsure to suggest. */
const SUGGEST_MIN = 0.35;

const SKIP_LABELS: Record<string, string> = {
    not_braille: 'Not braille',
    unreadable: 'Photo unreadable',
    rotated: 'Turned or upside down',
    duplicate: 'Duplicate',
    other: 'Other',
};

const TAG_LABELS: Record<string, { label: string; hint: string; key: string }> = {
    two_sided: { label: 'Two-sided', hint: 'The back’s dents show between the dots. Label only the raised side.', key: 'T' },
    side_light: { label: 'Light from below / side', hint: 'Raised dots can look like dents in this light.', key: 'L' },
};

const SHORTCUTS: [string, string][] = [
    ['Click a box', 'Select it; drag to move, corners to resize'],
    ['Drag on the photo', 'Draw a new cell'],
    ['Double-click / N', 'Add a cell of the usual size, centred on its dots'],
    ['B', 'Add a cell the size of the last one, next to the selected cell; drag it into place'],
    ['Click a red dashed box', 'Take the server’s cell'],
    ['1 – 6', 'Toggle that dot of the selected cell'],
    ['Tab / Shift+Tab', 'Next / previous cell in reading order'],
    ['V', 'The selected cell is right: mark it checked, go to the next unsure one'],
    ['U', 'Use the server’s dots for the selected cell'],
    ['C', 'Re-read the selected cell’s dots with the model'],
    ['A', 'Take all server suggestions'],
    ['Delete', 'Delete the selected cell'],
    ['Arrows', 'Nudge (Shift: 5 px)'],
    ['H / D', 'Hide boxes / dots'],
    ['Space + drag, scroll', 'Pan'],
    ['Pinch, ⌘ + scroll, + / −', 'Zoom; F fits'],
    ['⌘Z / ⇧⌘Z', 'Undo / redo'],
    ['T / L', 'Mark the page two-sided / lit from below or the side'],
    ['Enter', 'Save and go to the next page'],
];

/**
 * Unsaved work on a page, kept in this browser after every change so a lost
 * session or a closed tab does not lose it. `base` is when the page was last
 * saved: a draft older than a later save is dropped.
 */
interface Draft {
    base: string | null;
    savedAt: number;
    cells: LabelCell[];
    rejected: number[];
    tags?: string[];
}

const draftKey = (id: number) => `labeling-draft:${id}`;

function readDraft(id: number): Draft | null {
    try {
        const raw = localStorage.getItem(draftKey(id));
        return raw ? (JSON.parse(raw) as Draft) : null;
    } catch {
        return null;
    }
}

function writeDraft(id: number, draft: Draft) {
    try {
        localStorage.setItem(draftKey(id), JSON.stringify(draft));
    } catch {
        // Storage full or blocked: the page still works, only without the safety net.
    }
}

function clearDraft(id: number) {
    try {
        localStorage.removeItem(draftKey(id));
    } catch {
        // Nothing to clear.
    }
}

function formatHours(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.round((seconds % 3600) / 60);
    return h ? `${h} h ${m} min` : `${m} min`;
}

export default function Labeling({ startId, stats: initialStats, skipReasons, tagNames, langs, classifierUrl, readerInstalled }: LabelingProps) {
    const { routes } = usePage<SharedProps>().props;
    const [pageId, setPageId] = useState<number | null>(startId);
    const [page, setPage] = useState<PageData | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [stats, setStats] = useState(initialStats);
    const [cells, setCells] = useState<LabelCell[]>([]);
    const initialCells = useRef<LabelCell[]>([]);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [rejected, setRejected] = useState<Set<number>>(new Set());
    const [tags, setTags] = useState<string[]>([]);
    const [showBoxes, setShowBoxes] = useState(true);
    const [showDots, setShowDots] = useState(true);
    const [showHelp, setShowHelp] = useState(false);
    const [saving, setSaving] = useState(false);
    const [notice, setNotice] = useState<ReactNode>(null);
    const [restoredAt, setRestoredAt] = useState<number | null>(null);
    const [lang, setLang] = useState('EN');
    const [reading, setReading] = useState<{ text: string; braille: string } | null>(null);
    const [readingBusy, setReadingBusy] = useState(false);
    const [dirty, setDirty] = useState(false);
    const undo = useRef<LabelCell[][]>([]);
    const redo = useRef<LabelCell[][]>([]);
    const nextId = useRef(1);
    /** The cell drawn, added or reshaped last: B copies its size. */
    const lastBox = useRef<Box | null>(null);
    const editor = useRef<EditorHandle>(null);
    const gray = useRef<{ id: number; photo: Promise<GrayPhoto> } | null>(null);
    const active = useRef({ seconds: 0, last: Date.now() });
    const cellsRef = useRef(cells);
    cellsRef.current = cells;

    const pageUrl = (id: number) => `${routes.labeling}/${id}`;

    const signedOut = (
        <span>
            You were signed out. Your changes are kept in this browser:{' '}
            <a href={routes.login} target="_blank" rel="noreferrer" className="font-medium underline">
                sign in again
            </a>{' '}
            in the new tab, then come back and save.
        </span>
    );

    // Active time only: gaps over a minute are breaks, not labeling.
    const touch = useCallback(() => {
        const now = Date.now();
        active.current.seconds += Math.min(now - active.current.last, 60_000) / 1000;
        active.current.last = now;
    }, []);

    useEffect(() => {
        if (!pageId) return;
        let cancelled = false;
        setLoading(true);
        setError(null);
        fetch(pageUrl(pageId), { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : Promise.reject(r.status === 401 ? new SignedOut() : new Error(`Request failed with ${r.status}`))))
            .then(({ data }: { data: PageData }) => {
                if (cancelled) return;
                let id = 1;
                let loaded: LabelCell[];
                if (data.cells) {
                    loaded = data.cells.map((c) => ({ ...boxOf(c), id: id++, pattern: c[4], origin: 'human', dotsByHand: true }));
                } else {
                    loaded = data.model_cells.map((c) => ({ ...boxOf(c), id: id++, pattern: c[4], origin: 'model', score: c[5], margin: c[6] }));
                    // Where both found a cell but read different dots, keep the server's reading to offer.
                    const sure = data.suggestions.filter((s) => s[5] >= 0.5);
                    for (const [i, j] of match(loaded, sure.map(boxOf))) {
                        if (sure[j][4] !== loaded[i].pattern) loaded[i].serverPattern = sure[j][4];
                    }
                }
                initialCells.current = loaded;
                // Unsaved work from before (a lost session, a closed tab) picks up where it stopped.
                const draft = readDraft(data.id);
                const restore = draft && draft.base === (data.labeled_at ?? null) ? draft : null;
                if (draft && !restore) clearDraft(data.id);
                const start = restore ? restore.cells : loaded;
                nextId.current = Math.max(id, ...start.map((c) => c.id + 1));
                undo.current = [];
                redo.current = [];
                active.current = { seconds: 0, last: Date.now() };
                setPage(data);
                setCells(start);
                setSelectedId(null);
                setRejected(new Set(restore?.rejected ?? []));
                setTags(restore?.tags ?? data.tags ?? []);
                setReading(null);
                setDirty(Boolean(restore));
                setRestoredAt(restore?.savedAt ?? null);
                setLang(langs.includes(data.lang) ? data.lang : 'EN');
            })
            .catch((e: Error) => !cancelled && (e instanceof SignedOut ? (setError(null), setNotice(signedOut)) : setError(e.message)))
            .finally(() => !cancelled && setLoading(false));
        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pageId]);

    // Every change goes to the draft, shortly after it is made.
    useEffect(() => {
        if (!page || !dirty) return;
        const timer = window.setTimeout(
            () => writeDraft(page.id, { base: page.labeled_at ?? null, savedAt: Date.now(), cells, rejected: [...rejected], tags }),
            300,
        );
        return () => window.clearTimeout(timer);
    }, [page, cells, rejected, tags, dirty]);

    const discardDraft = () => {
        if (!page) return;
        clearDraft(page.id);
        undo.current.push(cellsRef.current);
        setCells(initialCells.current);
        setRejected(new Set());
        setTags(page.tags ?? []);
        setDirty(false);
        setRestoredAt(null);
    };

    useEffect(() => {
        if (pageId) window.history.replaceState(null, '', `${routes.labeling}?page=${pageId}`);
    }, [pageId, routes.labeling]);

    const suggestions = useMemo(() => {
        if (!page) return [];
        const candidates = page.suggestions.map((cell, index) => ({ cell, index })).filter(({ cell, index }) => cell[5] >= SUGGEST_MIN && !rejected.has(index));
        const covered = new Set(match(candidates.map(({ cell }) => boxOf(cell)), cells, 0.2).map(([i]) => i));
        return candidates.filter((_, i) => !covered.has(i));
    }, [page, cells, rejected]);

    const ordered = useMemo(() => readingOrder(cells), [cells]);
    const selected = cells.find((c) => c.id === selectedId) ?? null;
    const size = useMemo(() => typicalSize(cells.length ? cells : (page?.suggestions ?? []).map(boxOf), page?.width ?? 1000), [cells, page]);

    // ---- editing ----
    const snapshot = useCallback(() => {
        undo.current.push(cellsRef.current);
        if (undo.current.length > 200) undo.current.shift();
        redo.current = [];
        setDirty(true);
        touch();
    }, [touch]);

    const toggleTag = useCallback(
        (tag: string) => {
            setTags((ts) => (ts.includes(tag) ? ts.filter((t) => t !== tag) : [...ts, tag]));
            setDirty(true);
            touch();
        },
        [touch],
    );

    const update = useCallback((id: number, change: Partial<LabelCell>) => {
        setCells((cs) => cs.map((c) => (c.id === id ? { ...c, ...change } : c)));
    }, []);

    const loadGray = useCallback(() => {
        if (!page?.photo_url) return null;
        if (gray.current?.id !== page.id) gray.current = { id: page.id, photo: grayPhoto(page.photo_url, page.width, page.height) };
        return gray.current.photo;
    }, [page]);

    /** Reads the dots of these cells with the model, unless they were set by hand meanwhile. */
    const readDots = useCallback(
        async (targets: LabelCell[], force = false) => {
            const photo = loadGray();
            if (!classifierUrl || !photo || targets.length === 0) return;
            try {
                const results = await classify(classifierUrl, await photo, targets);
                setCells((cs) =>
                    cs.map((c) => {
                        const k = targets.findIndex((t) => t.id === c.id);
                        if (k < 0 || (c.dotsByHand && !force)) return c;
                        return { ...c, pattern: results[k].pattern, margin: results[k].margin, dotsByHand: force ? false : c.dotsByHand };
                    }),
                );
            } catch (e) {
                setNotice(`The dot model could not run: ${(e as Error).message}`);
            }
        },
        [classifierUrl, loadGray],
    );

    const addCell = useCallback(
        (box: Box, from?: Partial<LabelCell>, read = true) => {
            snapshot();
            const id = nextId.current++;
            const cell: LabelCell = { ...box, id, pattern: 0, origin: 'human', ...from };
            lastBox.current = box;
            setCells((cs) => [...cs, cell]);
            setSelectedId(id);
            if (read && !cell.pattern) void readDots([cell]);
            return id;
        },
        [snapshot, readDots],
    );

    /**
     * A cell the size of the last one drawn or reshaped, just right of the
     * selected cell (or of that last one), to be dragged into place: quicker
     * than drawing when the boxes need adjusting anyway. Its dots are read
     * again where it is dropped.
     */
    const addNext = useCallback(() => {
        const ref = cellsRef.current.find((c) => c.id === selectedId) ?? lastBox.current;
        const w = lastBox.current ? lastBox.current.x2 - lastBox.current.x1 : size.w;
        const h = lastBox.current ? lastBox.current.y2 - lastBox.current.y1 : size.h;
        let x1: number, cy: number;
        if (ref) {
            x1 = ref.x2 + (ref.x2 - ref.x1) * 0.3;
            cy = (ref.y1 + ref.y2) / 2;
        } else {
            const p = editor.current?.pointer();
            if (!p) return;
            x1 = p.x - w / 2;
            cy = p.y;
        }
        const box = { x1, y1: cy - h / 2, x2: x1 + w, y2: cy + h / 2 };
        addCell(box);
        editor.current?.reveal(box);
    }, [selectedId, size, addCell]);

    /** Deletes every cell of one colour at once, as one undo step. */
    const removeWhere = useCallback(
        (keep: (c: LabelCell) => boolean) => {
            snapshot();
            setCells((cs) => cs.filter(keep));
            setSelectedId(null);
        },
        [snapshot],
    );

    /** A cell of the page's usual size at a point, centred on its dots by the model when it is available. */
    const addAt = useCallback(
        async (x: number, y: number) => {
            const id = addCell({ x1: x - size.w / 2, y1: y - size.h / 2, x2: x + size.w / 2, y2: y + size.h / 2 }, { pattern: 0 }, false);
            const photo = loadGray();
            if (!classifierUrl || !photo) return;
            try {
                const placed = await snap(classifierUrl, await photo, { x1: x - size.w / 2, y1: y - size.h / 2, x2: x + size.w / 2, y2: y + size.h / 2 });
                setCells((cs) => cs.map((c) => (c.id === id && !c.dotsByHand && !c.moved ? { ...c, ...placed.box, pattern: placed.pattern, margin: placed.margin } : c)));
            } catch (e) {
                setNotice(`The dot model could not run: ${(e as Error).message}`);
            }
        },
        [addCell, size, classifierUrl, loadGray],
    );

    const accept = useCallback(
        (index: number) => {
            const s = page?.suggestions[index];
            if (s) addCell(boxOf(s), { pattern: s[4], origin: 'server' });
        },
        [page, addCell],
    );

    const acceptAll = useCallback(() => {
        if (suggestions.length === 0) return;
        snapshot();
        setCells((cs) => [...cs, ...suggestions.map(({ cell }) => ({ ...boxOf(cell), id: nextId.current++, pattern: cell[4], origin: 'server' as const }))]);
    }, [suggestions, snapshot]);

    const toggleDot = useCallback(
        (id: number, dot: number) => {
            const c = cellsRef.current.find((x) => x.id === id);
            if (!c) return;
            snapshot();
            update(id, { pattern: c.pattern ^ (1 << dot), dotsByHand: true });
        },
        [snapshot, update],
    );

    const remove = useCallback(
        (id: number) => {
            const i = ordered.findIndex((c) => c.id === id);
            snapshot();
            setCells((cs) => cs.filter((c) => c.id !== id));
            setSelectedId(ordered[i + 1]?.id ?? ordered[i - 1]?.id ?? null);
        },
        [ordered, snapshot],
    );

    const step = useCallback(
        (dir: 1 | -1) => {
            if (ordered.length === 0) return;
            const i = ordered.findIndex((c) => c.id === selectedId);
            const next = ordered[(i < 0 ? (dir > 0 ? 0 : ordered.length - 1) : (i + dir + ordered.length) % ordered.length)];
            setSelectedId(next.id);
            editor.current?.reveal(next);
        },
        [ordered, selectedId],
    );

    /** The next unsure cell after [fromId] in reading order, wrapping round. */
    const nextUnsure = useCallback(
        (fromId: number | null, list: LabelCell[] = ordered) => {
            const i = list.findIndex((c) => c.id === fromId);
            for (let k = 1; k <= list.length; k++) {
                const c = list[(Math.max(i, -1) + k + list.length) % list.length];
                if (isUnsure(c) && c.id !== fromId) return c;
            }
            return null;
        },
        [ordered],
    );

    const goTo = useCallback((c: LabelCell | null) => {
        if (!c) return;
        setSelectedId(c.id);
        editor.current?.reveal(c);
    }, []);

    /** The phone got this cell right: mark it checked and move on to the next one it was unsure of. */
    const confirm = useCallback(
        (id: number) => {
            snapshot();
            const list = ordered.map((c) => (c.id === id ? { ...c, checked: true } : c));
            update(id, { checked: true });
            const next = nextUnsure(id, list);
            if (next) goTo(next);
            else setSelectedId(null);
        },
        [ordered, snapshot, update, nextUnsure, goTo],
    );

    const undoLast = useCallback(() => {
        const prev = undo.current.pop();
        if (!prev) return;
        redo.current.push(cellsRef.current);
        setCells(prev);
    }, []);

    const redoLast = useCallback(() => {
        const next = redo.current.pop();
        if (!next) return;
        undo.current.push(cellsRef.current);
        setCells(next);
    }, []);

    // ---- saving ----
    const save = useCallback(async () => {
        if (!page || saving) return;
        const blank = ordered.find((c) => c.pattern === 0);
        if (blank) {
            setSelectedId(blank.id);
            editor.current?.reveal(blank);
            setNotice('This cell has no raised dots. Set its dots, or delete it if it is not a cell.');
            return;
        }
        touch();
        setSaving(true);
        try {
            const result = await send<{ next_id: number | null; stats: Stats }>(pageUrl(page.id), 'PUT', {
                cells: cells.map((c) => [c.x1, c.y1, c.x2, c.y2, c.pattern]),
                edits: editStats(initialCells.current, cells),
                tags,
                seconds: Math.round(active.current.seconds),
            });
            clearDraft(page.id);
            setDirty(false);
            setStats(result.stats);
            setNotice(null);
            setPageId(result.next_id);
            if (!result.next_id) setPage(null);
        } catch (e) {
            setNotice(e instanceof SignedOut ? signedOut : (e as Error).message);
        } finally {
            setSaving(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, cells, ordered, saving, touch, tags]);

    const skip = async (reason: string) => {
        if (!page) return;
        touch();
        try {
            const result = await send<{ next_id: number | null; stats: Stats }>(`${pageUrl(page.id)}/skip`, 'POST', { reason, seconds: Math.round(active.current.seconds) });
            clearDraft(page.id);
            setDirty(false);
            setStats(result.stats);
            setPageId(result.next_id);
            if (!result.next_id) setPage(null);
        } catch (e) {
            setNotice(e instanceof SignedOut ? signedOut : (e as Error).message);
        }
    };

    const readText = useCallback(async () => {
        if (!page) return;
        setReadingBusy(true);
        try {
            const result = await send<{ data: { text: string; braille: string } }>(`${pageUrl(page.id)}/read`, 'POST', {
                lang,
                cells: cells.filter((c) => c.pattern > 0).map((c) => [c.x1, c.y1, c.x2, c.y2, c.pattern]),
            });
            setReading(result.data);
        } catch (e) {
            setNotice(e instanceof SignedOut ? signedOut : (e as Error).message);
        } finally {
            setReadingBusy(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, cells, lang]);

    // ---- keyboard ----
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            const t = e.target as HTMLElement;
            if (t.closest('input, textarea, select, [role="listbox"], [role="menu"]')) return;
            touch();
            const mod = e.metaKey || e.ctrlKey;
            const key = e.key.toLowerCase();
            if (mod && key === 'z') {
                e.preventDefault();
                if (e.shiftKey) redoLast();
                else undoLast();
                return;
            }
            if (mod) return;
            if (selected && /^[1-6]$/.test(e.key)) {
                toggleDot(selected.id, Number(e.key) - 1);
            } else if (e.key === 'Tab') {
                e.preventDefault();
                step(e.shiftKey ? -1 : 1);
            } else if (selected && (e.key === 'Delete' || e.key === 'Backspace')) {
                e.preventDefault();
                remove(selected.id);
            } else if (selected && e.key.startsWith('Arrow')) {
                e.preventDefault();
                const d = e.shiftKey ? 5 : 1;
                const dx = e.key === 'ArrowLeft' ? -d : e.key === 'ArrowRight' ? d : 0;
                const dy = e.key === 'ArrowUp' ? -d : e.key === 'ArrowDown' ? d : 0;
                snapshot();
                update(selected.id, { x1: selected.x1 + dx, x2: selected.x2 + dx, y1: selected.y1 + dy, y2: selected.y2 + dy, moved: true });
            } else if (key === 'b') {
                addNext();
            } else if (key === 'n') {
                const p = editor.current?.pointer();
                if (p) void addAt(p.x, p.y);
            } else if (key === 'a') {
                acceptAll();
            } else if (key === 'v' && selected) {
                confirm(selected.id);
            } else if (key === 'u' && selected?.serverPattern !== undefined) {
                snapshot();
                update(selected.id, { pattern: selected.serverPattern, dotsByHand: true });
            } else if (key === 'c' && selected) {
                snapshot();
                void readDots([selected], true);
            } else if (key === 't' && tagNames.includes('two_sided')) {
                toggleTag('two_sided');
            } else if (key === 'l' && tagNames.includes('side_light')) {
                toggleTag('side_light');
            } else if (key === 'h') {
                setShowBoxes((v) => !v);
            } else if (key === 'd') {
                setShowDots((v) => !v);
            } else if (key === 'f') {
                editor.current?.fit();
            } else if (e.key === '+' || e.key === '=') {
                editor.current?.zoomBy(1.25);
            } else if (e.key === '-') {
                editor.current?.zoomBy(0.8);
            } else if (e.key === 'Escape') {
                setSelectedId(null);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                void save();
            } else if (e.key === '?') {
                setShowHelp((v) => !v);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [selected, toggleDot, step, remove, snapshot, update, addAt, acceptAll, readDots, save, undoLast, redoLast, touch, toggleTag, tagNames, confirm, addNext]);

    useEffect(() => {
        const warn = (e: BeforeUnloadEvent) => {
            if (dirty) e.preventDefault();
        };
        window.addEventListener('beforeunload', warn);
        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);

    // ---- view ----
    const total = (s: SplitStats) => s.todo + s.done + s.skipped;
    const unsureLeft = cells.filter(isUnsure).length;

    const actions = (
        <div className="flex items-center gap-1.5">
            <Button variant="ghost" size="sm" disabled={!page?.prev_id} onClick={() => page?.prev_id && setPageId(page.prev_id)} title="The page labeled before this one">
                <ArrowLeft />
                Previous
            </Button>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline" size="sm" disabled={!page}>
                        Skip
                        <ChevronDown />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {skipReasons.map((r) => (
                        <DropdownMenuItem key={r} onSelect={() => void skip(r)}>
                            {SKIP_LABELS[r] ?? r}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
            <Button size="sm" disabled={!page || saving} onClick={() => void save()}>
                {saving ? <LoaderCircle className="animate-spin" /> : <Check />}
                Save &amp; next
            </Button>
        </div>
    );

    const toolbar = (
        <div className="flex min-w-0 flex-1 items-center gap-4 overflow-x-auto text-xs whitespace-nowrap text-muted-foreground">
            <span>
                Train <span className="font-medium text-foreground tabular-nums">{stats.train.done}</span> / {total(stats.train)}
            </span>
            <span>
                Test <span className="font-medium text-foreground tabular-nums">{stats.test.done}</span> / {total(stats.test)}
            </span>
            <span>
                Today <span className="font-medium text-foreground tabular-nums">{stats.today}</span>
            </span>
            <span>
                <span className="font-medium text-foreground tabular-nums">{stats.cells.toLocaleString()}</span> cells · {formatHours(stats.seconds)}
            </span>
            {page && (
                <span className="ml-auto flex items-center gap-1.5">
                    {page.split === 'test' && (
                        <Badge variant="secondary" title="Test pages are never trained on: they measure the model on people it has not seen">
                            Test page
                        </Badge>
                    )}
                    {page.status !== 'todo' && (
                        <Badge variant="outline">
                            {page.status === 'done' ? `Labeled${page.labeled_by ? ` by ${page.labeled_by}` : ''}` : `Skipped: ${SKIP_LABELS[page.skip_reason ?? ''] ?? page.skip_reason}`}
                        </Badge>
                    )}
                    <span>
                        Page #{page.id} · scan {page.translation_id}
                    </span>
                </span>
            )}
        </div>
    );

    return (
        <DashboardLayout title="Labeling" description="Correct the phone model's cells to train the next one" actions={actions} toolbar={toolbar} flush>
            <Head title="Labeling" />
            {!pageId ? (
                <div className="flex flex-1 items-center justify-center p-6 text-sm text-muted-foreground">
                    Nothing left to label. New phone scans join the queue with <code className="mx-1 rounded bg-muted px-1">php artisan labels:queue</code>.
                </div>
            ) : (
                <div className="flex min-h-0 flex-1">
                    <div className="relative min-w-0 flex-1">
                        {page && (
                            <Editor
                                ref={editor}
                                photoUrl={page.photo_url}
                                width={page.width}
                                height={page.height}
                                cells={cells}
                                suggestions={suggestions}
                                selectedId={selectedId}
                                showBoxes={showBoxes}
                                showDots={showDots}
                                onSelect={setSelectedId}
                                onBeginEdit={snapshot}
                                onMove={(id, box) => update(id, { ...box, moved: true })}
                                onEndMove={(id) => {
                                    const moved = cellsRef.current.filter((c) => c.id === id);
                                    if (moved[0]) lastBox.current = { x1: moved[0].x1, y1: moved[0].y1, x2: moved[0].x2, y2: moved[0].y2 };
                                    void readDots(moved);
                                }}
                                onToggleDot={toggleDot}
                                onCreate={(box) => addCell(box)}
                                onCreateAt={(x, y) => void addAt(x, y)}
                                onAccept={accept}
                            />
                        )}
                        {(loading || error) && (
                            <div className="absolute inset-0 flex items-center justify-center bg-background/60 text-sm">
                                {loading ? <LoaderCircle className="size-5 animate-spin" /> : <span className="text-destructive">{error}</span>}
                            </div>
                        )}
                        <div className="absolute bottom-3 left-3 flex gap-1 rounded-lg border bg-background/90 p-1 shadow-sm backdrop-blur">
                            <Button variant="ghost" size="icon-sm" title="Undo (⌘Z)" onClick={undoLast}>
                                <Undo2 />
                            </Button>
                            <Button variant="ghost" size="icon-sm" title="Redo (⇧⌘Z)" onClick={redoLast}>
                                <Redo2 />
                            </Button>
                            <Button variant="ghost" size="icon-sm" title="Fit (F)" onClick={() => editor.current?.fit()}>
                                <Maximize />
                            </Button>
                            <Button variant="ghost" size="icon-sm" title="Hide boxes (H)" onClick={() => setShowBoxes((v) => !v)}>
                                {showBoxes ? <Eye /> : <EyeOff />}
                            </Button>
                            <Button variant="ghost" size="icon-sm" title="Shortcuts (?)" onClick={() => setShowHelp((v) => !v)}>
                                <Keyboard />
                            </Button>
                        </div>
                        {notice && (
                            <div className="absolute top-3 left-1/2 flex max-w-[90%] -translate-x-1/2 items-center gap-2 rounded-md border bg-background px-3 py-2 text-sm shadow-md">
                                {notice}
                                <Button variant="ghost" size="icon-xs" onClick={() => setNotice(null)}>
                                    <X />
                                </Button>
                            </div>
                        )}
                        {showHelp && (
                            <div className="absolute bottom-14 left-3 w-[26rem] rounded-lg border bg-background p-3 text-xs shadow-lg">
                                <table className="w-full">
                                    <tbody>
                                        {SHORTCUTS.map(([k, v]) => (
                                            <tr key={k}>
                                                <td className="py-0.5 pr-3 font-medium whitespace-nowrap">{k}</td>
                                                <td className="py-0.5 text-muted-foreground">{v}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <aside className="flex w-80 shrink-0 flex-col gap-5 overflow-y-auto border-l p-4 text-sm">
                        {page && (
                            <>
                                {restoredAt && (
                                    <div className="flex items-center justify-between gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 px-2.5 py-2 text-xs">
                                        <span>
                                            Unsaved changes from{' '}
                                            {new Date(restoredAt).toLocaleString(undefined, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })} restored.
                                        </span>
                                        <Button variant="ghost" size="xs" onClick={discardDraft}>
                                            Discard
                                        </Button>
                                    </div>
                                )}
                                <section className="flex flex-col gap-2">
                                    <h2 className="text-xs font-medium text-muted-foreground">This page is</h2>
                                    <div className="flex flex-wrap gap-1.5">
                                        {tagNames.map((t) => {
                                            const on = tags.includes(t);
                                            const info = TAG_LABELS[t];
                                            return (
                                                <Button
                                                    key={t}
                                                    variant={on ? 'default' : 'outline'}
                                                    size="xs"
                                                    aria-pressed={on}
                                                    title={`${info?.hint ?? ''} (${info?.key ?? ''})`}
                                                    onClick={() => toggleTag(t)}
                                                >
                                                    {on && <Check />}
                                                    {info?.label ?? t}
                                                </Button>
                                            );
                                        })}
                                    </div>
                                    {tags.includes('two_sided') && <p className="text-xs text-muted-foreground">{TAG_LABELS.two_sided.hint}</p>}
                                </section>

                                <section className="flex flex-col gap-2">
                                    <h2 className="text-xs font-medium text-muted-foreground">On this page</h2>
                                    <div className="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                        <Legend
                                            color={COLORS.model}
                                            label={`Phone · ${cells.filter((c) => !isChecked(c) && !isUnsure(c)).length}`}
                                            onDelete={() => removeWhere((c) => isChecked(c) || isUnsure(c))}
                                            deleteTitle="Delete all blue cells (the phone's sure ones)"
                                        />
                                        <Legend
                                            color={COLORS.unsure}
                                            label={`Unsure · ${unsureLeft}`}
                                            onDelete={() => removeWhere((c) => !isUnsure(c))}
                                            deleteTitle="Delete all yellow cells"
                                        />
                                        <Legend color={COLORS.human} label={`Checked · ${cells.filter(isChecked).length}`} />
                                        <Legend
                                            color={COLORS.server}
                                            dashed
                                            label={`Server only · ${suggestions.length}`}
                                            onDelete={() => setRejected(new Set([...rejected, ...suggestions.map((x) => x.index)]))}
                                            deleteTitle="Hide all red suggestions"
                                        />
                                    </div>
                                    {suggestions.length > 0 && (
                                        <div className="flex gap-1.5">
                                            <Button variant="outline" size="xs" onClick={acceptAll}>
                                                <Sparkles />
                                                Take all {suggestions.length} (A)
                                            </Button>
                                            <Button variant="ghost" size="xs" onClick={() => setRejected(new Set([...rejected, ...suggestions.map((s) => s.index)]))}>
                                                Dismiss
                                            </Button>
                                        </div>
                                    )}
                                    {unsureLeft > 0 && (
                                        <Button
                                            variant="ghost"
                                            size="xs"
                                            className="self-start"
                                            onClick={() => goTo(nextUnsure(selectedId))}
                                        >
                                            Go to the next unsure cell
                                        </Button>
                                    )}
                                </section>

                                <section className="flex flex-col gap-3">
                                    <h2 className="text-xs font-medium text-muted-foreground">Selected cell</h2>
                                    {selected ? (
                                        <>
                                            <Loupe photoUrl={page.photo_url} box={selected} width={page.width} height={page.height} pattern={selected.pattern} />
                                            <DotPad pattern={selected.pattern} serverPattern={selected.serverPattern} onToggle={(d) => toggleDot(selected.id, d)} />
                                            <div className="flex flex-col gap-1 text-xs text-muted-foreground">
                                                <span>
                                                    {selected.origin === 'model' ? 'Found by the phone' : selected.origin === 'server' ? 'Taken from the server' : 'Added by hand'}
                                                    {selected.score !== undefined && ` · detector ${selected.score.toFixed(2)}`}
                                                    {selected.margin !== undefined && Number.isFinite(selected.margin) && ` · dots ${selected.margin.toFixed(1)}`}
                                                </span>
                                                {selected.serverPattern !== undefined && (
                                                    <span className="flex items-center gap-2">
                                                        <span>
                                                            Server reads <span className="text-base text-foreground">{String.fromCodePoint(0x2800 + selected.serverPattern)}</span>
                                                        </span>
                                                        <Button
                                                            variant="outline"
                                                            size="xs"
                                                            onClick={() => {
                                                                snapshot();
                                                                update(selected.id, { pattern: selected.serverPattern, dotsByHand: true });
                                                            }}
                                                        >
                                                            Use (U)
                                                        </Button>
                                                    </span>
                                                )}
                                            </div>
                                            <div className="flex flex-wrap gap-1.5">
                                                {isUnsure(selected) && (
                                                    <Button size="xs" onClick={() => confirm(selected.id)}>
                                                        <Check />
                                                        Correct (V)
                                                    </Button>
                                                )}
                                                {classifierUrl && (
                                                    <Button
                                                        variant="outline"
                                                        size="xs"
                                                        onClick={() => {
                                                            snapshot();
                                                            void readDots([selected], true);
                                                        }}
                                                    >
                                                        Re-read dots (C)
                                                    </Button>
                                                )}
                                                <Button variant="destructive" size="xs" onClick={() => remove(selected.id)}>
                                                    <Trash2 />
                                                    Delete
                                                </Button>
                                            </div>
                                        </>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            Click a box, or press Tab to walk the cells in reading order. Drag on the photo to add a missing cell.
                                        </p>
                                    )}
                                </section>

                                <section className="flex flex-col gap-2">
                                    <h2 className="text-xs font-medium text-muted-foreground">Text</h2>
                                    {readerInstalled ? (
                                        <>
                                            <div className="flex gap-1.5">
                                                <Select value={lang} onValueChange={setLang}>
                                                    <SelectTrigger size="sm" className="flex-1">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {langs.map((l) => (
                                                            <SelectItem key={l} value={l}>
                                                                {languageName(l)}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <Button variant="outline" size="sm" onClick={() => void readText()} disabled={readingBusy}>
                                                    {readingBusy && <LoaderCircle className="animate-spin" />}
                                                    Read
                                                </Button>
                                            </div>
                                            <p className="rounded-md bg-muted p-2 text-xs whitespace-pre-wrap">{reading ? reading.text || '(nothing)' : page.text?.trim() || '(nothing)'}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {reading ? `The cells as they are now, read as ${languageName(lang)}.` : 'What the phone read. Read shows the cells as they are now.'}
                                            </p>
                                        </>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">The braille reader is not installed here.</p>
                                    )}
                                </section>

                                {!classifierUrl && (
                                    <p className="text-xs text-muted-foreground">
                                        The dot model is not installed, so new cells start without dots. Put it at <code>storage/{'app/labeling/dot_classifier.onnx'}</code>.
                                    </p>
                                )}
                            </>
                        )}
                    </aside>
                </div>
            )}
        </DashboardLayout>
    );
}

function Legend({ color, label, dashed, onDelete, deleteTitle }: { color: string; label: string; dashed?: boolean; onDelete?: () => void; deleteTitle?: string }) {
    return (
        <span className="flex items-center gap-1.5">
            <span className="size-3 shrink-0 rounded-sm border-2" style={{ borderColor: color, borderStyle: dashed ? 'dashed' : 'solid' }} />
            {label}
            {onDelete && !label.endsWith(' 0') && (
                <button type="button" title={deleteTitle} onClick={onDelete} className="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-destructive">
                    <Trash2 className="size-3" />
                </button>
            )}
        </span>
    );
}
