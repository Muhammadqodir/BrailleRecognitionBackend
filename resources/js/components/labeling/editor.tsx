import { forwardRef, useCallback, useEffect, useImperativeHandle, useLayoutEffect, useRef, useState, type PointerEvent as ReactPointerEvent } from 'react';

import { dotPosition, isChecked, isUnsure, type Box, type LabelCell, type Suggestion } from './cells';

export const COLORS = {
    model: '#009AD1',
    unsure: '#F59E0B',
    human: '#16A34A',
    server: '#E11D48',
    selected: '#7C3AED',
};

export interface EditorHandle {
    fit: () => void;
    zoomBy: (factor: number) => void;
    /** Pans so the box is on screen, zooming in if the view is too far out to see its dots. */
    reveal: (box: Box) => void;
    /** The photo point under the mouse, if it is over the photo. */
    pointer: () => { x: number; y: number } | null;
}

interface EditorProps {
    photoUrl: string | null;
    width: number;
    height: number;
    cells: LabelCell[];
    /** Server cells no current cell covers, with their index in the page's suggestion list. */
    suggestions: { index: number; cell: Suggestion }[];
    /** The cell the panel shows: the last one selected. */
    selectedId: number | null;
    /** Every selected cell, including selectedId. */
    selectedIds: Set<number>;
    showBoxes: boolean;
    showDots: boolean;
    /** Additive (Shift) selection toggles the cell in or out of the selection. */
    onSelect: (id: number | null, additive?: boolean) => void;
    /** Shift + drag over empty photo: adds the cells inside the box to the selection. */
    onSelectBox: (box: Box) => void;
    /** A drag is about to change cells: snapshot for undo. */
    onBeginEdit: () => void;
    onMove: (boxes: { id: number; box: Box }[]) => void;
    /** A drag ended: the cells it changed, and where they were before it. */
    onEndMove: (ids: number[], from: Box[]) => void;
    onToggleDot: (id: number, dot: number) => void;
    onCreate: (box: Box) => void;
    onCreateAt: (x: number, y: number) => void;
    onAccept: (index: number) => void;
}

type Drag =
    | { kind: 'pan'; sx: number; sy: number; tx: number; ty: number }
    | { kind: 'move'; px: number; py: number; boxes: { id: number; box: Box }[]; started: boolean }
    | { kind: 'resize'; corner: number; px: number; py: number; boxes: { id: number; box: Box }[]; started: boolean }
    | { kind: 'draw'; px: number; py: number; x: number; y: number; marquee: boolean };

const HANDLE = 7; // screen px

/**
 * The photo with its cells over it, zoomable and pannable. Boxes, dots and
 * handles are drawn in photo pixels in one SVG layered on the image, so the
 * pointer only needs one transform back to photo coordinates.
 */
export const Editor = forwardRef<EditorHandle, EditorProps>(function Editor(props, ref) {
    const { photoUrl, width, height, cells, suggestions, selectedId, selectedIds, showBoxes, showDots } = props;
    const wrap = useRef<HTMLDivElement>(null);
    const [view, setView] = useState({ scale: 1, tx: 0, ty: 0 });
    const viewRef = useRef(view);
    viewRef.current = view;
    const drag = useRef<Drag | null>(null);
    const [drawBox, setDrawBox] = useState<Box | null>(null);
    /** The box being dragged out selects cells rather than drawing one. */
    const [marquee, setMarquee] = useState(false);
    const mouse = useRef<{ x: number; y: number } | null>(null);
    const space = useRef(false);
    const [panning, setPanning] = useState(false);
    const [failed, setFailed] = useState(false);
    const latest = useRef(props);
    latest.current = props;

    const fit = useCallback(() => {
        const el = wrap.current;
        if (!el || !width || !height) return;
        const pad = 16;
        const scale = Math.min((el.clientWidth - pad * 2) / width, (el.clientHeight - pad * 2) / height);
        setView({ scale, tx: (el.clientWidth - width * scale) / 2, ty: (el.clientHeight - height * scale) / 2 });
    }, [width, height]);

    useLayoutEffect(() => {
        fit();
        setFailed(false);
    }, [fit, photoUrl]);

    useEffect(() => {
        const el = wrap.current;
        if (!el) return;
        const observer = new ResizeObserver(() => fit());
        observer.observe(el);
        return () => observer.disconnect();
    }, [fit]);

    const zoomAt = useCallback((factor: number, cx: number, cy: number) => {
        setView((v) => {
            const scale = Math.min(Math.max(v.scale * factor, 0.05), 40);
            const f = scale / v.scale;
            return { scale, tx: cx - (cx - v.tx) * f, ty: cy - (cy - v.ty) * f };
        });
    }, []);

    useImperativeHandle(ref, () => ({
        fit,
        zoomBy: (factor) => {
            const el = wrap.current;
            if (el) zoomAt(factor, el.clientWidth / 2, el.clientHeight / 2);
        },
        reveal: (box) => {
            const el = wrap.current;
            if (!el) return;
            const v = viewRef.current;
            // Zoom in until a cell is at least ~36px tall on screen.
            const scale = Math.max(v.scale, 36 / Math.max(box.y2 - box.y1, 1));
            const cx = (box.x1 + box.x2) / 2;
            const cy = (box.y1 + box.y2) / 2;
            const onScreen =
                scale === v.scale &&
                box.x1 * scale + v.tx > 40 &&
                box.x2 * scale + v.tx < el.clientWidth - 40 &&
                box.y1 * scale + v.ty > 40 &&
                box.y2 * scale + v.ty < el.clientHeight - 40;
            if (!onScreen) setView({ scale, tx: el.clientWidth / 2 - cx * scale, ty: el.clientHeight / 2 - cy * scale });
        },
        pointer: () => mouse.current,
    }));

    // Wheel: pinch or Ctrl/Cmd zooms, plain scrolling pans (trackpads scroll both ways).
    useEffect(() => {
        const el = wrap.current;
        if (!el) return;
        const onWheel = (e: WheelEvent) => {
            e.preventDefault();
            const r = el.getBoundingClientRect();
            if (e.ctrlKey || e.metaKey) {
                zoomAt(Math.exp(-e.deltaY * 0.01), e.clientX - r.left, e.clientY - r.top);
            } else {
                setView((v) => ({ ...v, tx: v.tx - e.deltaX, ty: v.ty - e.deltaY }));
            }
        };
        el.addEventListener('wheel', onWheel, { passive: false });
        return () => el.removeEventListener('wheel', onWheel);
    }, [zoomAt]);

    useEffect(() => {
        const down = (e: KeyboardEvent) => {
            if (e.code === 'Space' && !(e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement)) {
                space.current = true;
                setPanning(true);
                e.preventDefault();
            }
        };
        const up = (e: KeyboardEvent) => {
            if (e.code === 'Space') {
                space.current = false;
                setPanning(false);
            }
        };
        window.addEventListener('keydown', down);
        window.addEventListener('keyup', up);
        return () => {
            window.removeEventListener('keydown', down);
            window.removeEventListener('keyup', up);
        };
    }, []);

    const toPhoto = (e: { clientX: number; clientY: number }) => {
        const r = wrap.current!.getBoundingClientRect();
        const v = viewRef.current;
        return { x: (e.clientX - r.left - v.tx) / v.scale, y: (e.clientY - r.top - v.ty) / v.scale };
    };

    const hitCell = (x: number, y: number): LabelCell | null => {
        // A selected cell first, then the smallest box under the pointer.
        const inside = (c: LabelCell) => x >= c.x1 && x <= c.x2 && y >= c.y1 && y <= c.y2;
        const sel = cells.find((c) => c.id === selectedId);
        if (sel && inside(sel)) return sel;
        const other = cells.find((c) => selectedIds.has(c.id) && inside(c));
        if (other) return other;
        let best: LabelCell | null = null;
        for (const c of cells) {
            if (x >= c.x1 && x <= c.x2 && y >= c.y1 && y <= c.y2 && (!best || (c.x2 - c.x1) * (c.y2 - c.y1) < (best.x2 - best.x1) * (best.y2 - best.y1))) best = c;
        }
        return best;
    };

    const onPointerDown = (e: ReactPointerEvent<HTMLDivElement>) => {
        const p = latest.current;
        e.currentTarget.setPointerCapture(e.pointerId);
        const v = viewRef.current;
        if (e.button === 1 || e.button === 2 || space.current) {
            drag.current = { kind: 'pan', sx: e.clientX, sy: e.clientY, tx: v.tx, ty: v.ty };
            setPanning(true);
            return;
        }
        if (e.button !== 0) return;
        const { x, y } = toPhoto(e);
        const tol = HANDLE / v.scale;
        const sel = cells.find((c) => c.id === selectedId);
        const group = cells.filter((c) => selectedIds.has(c.id));
        const boxesOf = (cs: LabelCell[]) => cs.map((c) => ({ id: c.id, box: { x1: c.x1, y1: c.y1, x2: c.x2, y2: c.y2 } }));

        if (showBoxes && !e.shiftKey) {
            // A corner handle of any selected cell resizes them all by the same amount.
            for (const c of sel ? [sel, ...group] : group) {
                const corners = [
                    [c.x1, c.y1],
                    [c.x2, c.y1],
                    [c.x2, c.y2],
                    [c.x1, c.y2],
                ];
                const corner = corners.findIndex(([cx, cy]) => Math.abs(cx - x) <= tol && Math.abs(cy - y) <= tol);
                if (corner >= 0) {
                    drag.current = { kind: 'resize', corner, px: corners[corner][0], py: corners[corner][1], boxes: boxesOf(group), started: false };
                    return;
                }
            }
        }

        if (sel && showBoxes && group.length === 1 && !e.shiftKey) {
            // A dot of the selected cell toggles it.
            if (showDots && x >= sel.x1 && x <= sel.x2 && y >= sel.y1 && y <= sel.y2) {
                const r = Math.max(Math.min(sel.x2 - sel.x1, sel.y2 - sel.y1) * 0.16, tol * 0.8);
                for (let d = 0; d < 6; d++) {
                    const { fx, fy } = dotPosition(d);
                    const dx = sel.x1 + (sel.x2 - sel.x1) * fx - x;
                    const dy = sel.y1 + (sel.y2 - sel.y1) * fy - y;
                    if (dx * dx + dy * dy <= r * r) {
                        p.onToggleDot(sel.id, d);
                        return;
                    }
                }
            }
        }

        const cell = showBoxes ? hitCell(x, y) : null;
        if (cell) {
            if (e.shiftKey) {
                // Shift toggles the cell; one just added can be dragged with the rest at once.
                const adding = !selectedIds.has(cell.id);
                p.onSelect(cell.id, true);
                if (adding) drag.current = { kind: 'move', px: x, py: y, boxes: boxesOf([...group, cell]), started: false };
                return;
            }
            // Dragging a selected cell drags the whole selection.
            const moving = selectedIds.has(cell.id) ? group : [cell];
            if (!selectedIds.has(cell.id)) p.onSelect(cell.id);
            drag.current = { kind: 'move', px: x, py: y, boxes: boxesOf(moving), started: false };
            return;
        }

        // A dashed suggestion: take it.
        if (showBoxes && !e.shiftKey) {
            const s = suggestions.find(({ cell: c }) => x >= c[0] && x <= c[2] && y >= c[1] && y <= c[3]);
            if (s) {
                p.onAccept(s.index);
                return;
            }
        }

        drag.current = { kind: 'draw', px: e.clientX, py: e.clientY, x, y, marquee: e.shiftKey };
    };

    const onPointerMove = (e: ReactPointerEvent<HTMLDivElement>) => {
        const pt = toPhoto(e);
        mouse.current = pt.x >= 0 && pt.y >= 0 && pt.x <= width && pt.y <= height ? pt : null;
        const d = drag.current;
        if (!d) return;
        const p = latest.current;
        if (d.kind === 'pan') {
            setView((v) => ({ ...v, tx: d.tx + e.clientX - d.sx, ty: d.ty + e.clientY - d.sy }));
        } else if (d.kind === 'move') {
            const dx = pt.x - d.px;
            const dy = pt.y - d.py;
            if (!d.started) {
                if (Math.hypot(dx, dy) * viewRef.current.scale < 3) return;
                d.started = true;
                p.onBeginEdit();
            }
            p.onMove(d.boxes.map(({ id, box: b }) => ({ id, box: { x1: b.x1 + dx, y1: b.y1 + dy, x2: b.x2 + dx, y2: b.y2 + dy } })));
        } else if (d.kind === 'resize') {
            if (!d.started) {
                d.started = true;
                p.onBeginEdit();
            }
            // The same corner of every selected cell moves by the pointer's offset.
            const dx = pt.x - d.px;
            const dy = pt.y - d.py;
            p.onMove(
                d.boxes.map(({ id, box }) => {
                    const b = { ...box };
                    if (d.corner === 0 || d.corner === 3) b.x1 = Math.min(box.x1 + dx, b.x2 - 2);
                    else b.x2 = Math.max(box.x2 + dx, b.x1 + 2);
                    if (d.corner === 0 || d.corner === 1) b.y1 = Math.min(box.y1 + dy, b.y2 - 2);
                    else b.y2 = Math.max(box.y2 + dy, b.y1 + 2);
                    return { id, box: b };
                }),
            );
        } else if (d.kind === 'draw') {
            if (Math.hypot(e.clientX - d.px, e.clientY - d.py) < 5) return;
            setMarquee(d.marquee);
            setDrawBox({ x1: Math.min(d.x, pt.x), y1: Math.min(d.y, pt.y), x2: Math.max(d.x, pt.x), y2: Math.max(d.y, pt.y) });
        }
    };

    const onPointerUp = () => {
        const d = drag.current;
        drag.current = null;
        const p = latest.current;
        if (!space.current) setPanning(false);
        if (!d) return;
        if ((d.kind === 'move' || d.kind === 'resize') && d.started) p.onEndMove(
                d.boxes.map((b) => b.id),
                d.boxes.map((b) => b.box),
            );
        if (d.kind === 'draw') {
            if (d.marquee) {
                if (drawBox) p.onSelectBox(drawBox);
            } else if (drawBox && drawBox.x2 - drawBox.x1 > 3 && drawBox.y2 - drawBox.y1 > 3) p.onCreate(drawBox);
            else p.onSelect(null);
            setDrawBox(null);
        }
    };

    const onDoubleClick = (e: React.MouseEvent) => {
        if (e.shiftKey) return;
        const { x, y } = toPhoto(e);
        if (!hitCell(x, y)) latest.current.onCreateAt(x, y);
    };

    const s = view.scale;
    const stroke = 1.5 / s;

    return (
        <div
            ref={wrap}
            className="relative h-full w-full touch-none overflow-hidden bg-neutral-900 select-none"
            style={{ cursor: panning ? 'grabbing' : 'crosshair' }}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onPointerCancel={onPointerUp}
            onPointerLeave={() => (mouse.current = null)}
            onDoubleClick={onDoubleClick}
            onContextMenu={(e) => e.preventDefault()}
        >
            <div className="absolute top-0 left-0 origin-top-left" style={{ width, height, transform: `translate(${view.tx}px, ${view.ty}px) scale(${s})` }}>
                {photoUrl && !failed ? (
                    <img src={photoUrl} alt="" draggable={false} className="absolute inset-0 size-full max-w-none" onError={() => setFailed(true)} />
                ) : (
                    <div className="absolute inset-0 flex items-center justify-center text-sm text-neutral-400">{photoUrl ? 'The photo could not be loaded' : 'No photo'}</div>
                )}
                {showBoxes && (
                    <svg className="absolute inset-0 size-full overflow-visible" viewBox={`0 0 ${width} ${height}`} aria-hidden>
                        {suggestions.map(({ index, cell: c }) => (
                            <rect
                                key={`s${index}`}
                                x={c[0]}
                                y={c[1]}
                                width={c[2] - c[0]}
                                height={c[3] - c[1]}
                                fill={COLORS.server}
                                fillOpacity={0.12}
                                stroke={COLORS.server}
                                strokeWidth={stroke}
                                strokeDasharray={`${4 / s} ${3 / s}`}
                            />
                        ))}
                        {cells.map((c) => {
                            const selected = selectedIds.has(c.id);
                            const primary = c.id === selectedId;
                            const color = selected ? COLORS.selected : isChecked(c) ? COLORS.human : isUnsure(c) ? COLORS.unsure : COLORS.model;
                            const w = c.x2 - c.x1;
                            const h = c.y2 - c.y1;
                            const r = Math.min(w, h);
                            return (
                                <g key={c.id}>
                                    <rect
                                        x={c.x1}
                                        y={c.y1}
                                        width={w}
                                        height={h}
                                        fill={selected ? COLORS.selected : 'none'}
                                        fillOpacity={0.08}
                                        stroke={color}
                                        strokeWidth={selected ? stroke * 1.6 : stroke}
                                    />
                                    {showDots &&
                                        [0, 1, 2, 3, 4, 5].map((d) => {
                                            const { fx, fy } = dotPosition(d);
                                            const raised = (c.pattern & (1 << d)) !== 0;
                                            return (
                                                <circle
                                                    key={d}
                                                    cx={c.x1 + w * fx}
                                                    cy={c.y1 + h * fy}
                                                    r={r * (primary ? 0.12 : 0.07)}
                                                    fill={raised ? color : 'none'}
                                                    fillOpacity={primary ? 0.75 : 0.9}
                                                    stroke={color}
                                                    strokeOpacity={raised ? 0 : 0.7}
                                                    strokeWidth={stroke * 0.6}
                                                />
                                            );
                                        })}
                                    {c.pattern === 0 && <text x={c.x1 + w / 2} y={c.y1 + h / 2} fontSize={r * 0.7} fill={COLORS.server} textAnchor="middle" dominantBaseline="central">?</text>}
                                    {selected &&
                                        [
                                            [c.x1, c.y1],
                                            [c.x2, c.y1],
                                            [c.x2, c.y2],
                                            [c.x1, c.y2],
                                        ].map(([hx, hy], i) => (
                                            <rect key={i} x={hx - 3.5 / s} y={hy - 3.5 / s} width={7 / s} height={7 / s} fill="white" stroke={COLORS.selected} strokeWidth={stroke} />
                                        ))}
                                </g>
                            );
                        })}
                        {drawBox && (
                            <rect
                                x={drawBox.x1}
                                y={drawBox.y1}
                                width={drawBox.x2 - drawBox.x1}
                                height={drawBox.y2 - drawBox.y1}
                                fill={marquee ? COLORS.selected : COLORS.human}
                                fillOpacity={marquee ? 0.06 : 0.1}
                                stroke={marquee ? COLORS.selected : COLORS.human}
                                strokeWidth={stroke}
                                strokeDasharray={marquee ? `${4 / s} ${3 / s}` : undefined}
                            />
                        )}
                    </svg>
                )}
            </div>
        </div>
    );
});
