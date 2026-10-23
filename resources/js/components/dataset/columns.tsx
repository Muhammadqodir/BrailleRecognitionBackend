import type { ColumnDef } from '@tanstack/react-table';

import { formatDateTime, Pill } from '@/components/badges';
import { DataTableColumnHeader, type SortDirection } from '@/components/data-table/data-table-column-header';
import { Checkbox } from '@/components/ui/checkbox';
import { ScanThumb } from '@/components/users/user-detail-dialog';
import { cn } from '@/lib/utils';

export interface DatasetRow {
    id: number;
    translation_id: number;
    image_url: string | null;
    split: 'train' | 'test';
    status: 'todo' | 'done' | 'skipped';
    skip_reason: string | null;
    tags: string[];
    cells: number | null;
    model_cells: number | null;
    issues: Record<string, number> | null;
    edits: Record<string, number> | null;
    priority: number;
    labeled_by: string | null;
    labeled_at: string | null;
    seconds: number;
}

export const TAG_LABELS: Record<string, string> = { two_sided: 'Two-sided', side_light: 'Light from below / side' };

export const STATUS_LABELS: Record<DatasetRow['status'], string> = { todo: 'To label', done: 'Labeled', skipped: 'Skipped' };

export const SKIP_LABELS: Record<string, string> = {
    not_braille: 'not braille',
    unreadable: 'unreadable',
    rotated: 'turned',
    duplicate: 'duplicate',
    other: 'other',
};

function duration(seconds: number): string {
    if (!seconds) return '—';
    return seconds < 60 ? `${seconds} s` : `${Math.round(seconds / 60)} min`;
}

/** What the admin changed against the phone's cells, or for a page not labeled yet, why it is in the queue. */
function changes(row: DatasetRow): string {
    if (row.status === 'done' && row.edits) {
        const e = row.edits;
        const parts = [
            e.added ? `+${e.added}` : null,
            e.deleted ? `−${e.deleted}` : null,
            e.moved ? `${e.moved} moved` : null,
            e.dots_changed ? `${e.dots_changed} dots` : null,
        ].filter(Boolean);
        return parts.length ? parts.join(' · ') : 'no changes';
    }
    if (row.status === 'todo' && row.issues) {
        const i = row.issues;
        const parts = [i.unsure ? `${i.unsure} unsure` : null, i.missed ? `${i.missed} missed` : null, i.dots ? `${i.dots} dots differ` : null].filter(Boolean);
        return parts.join(' · ') || '—';
    }
    return '—';
}

interface ColumnsOptions {
    sort: string;
    direction: SortDirection;
    onSort: (key: string, direction: SortDirection) => void;
    tagNames: string[];
    busy: Set<string>;
    onToggleTag: (row: DatasetRow, tag: string) => void;
}

export function getDatasetColumns({ sort, direction, onSort, tagNames, busy, onToggleTag }: ColumnsOptions): ColumnDef<DatasetRow>[] {
    return [
        {
            id: 'select',
            header: ({ table }) => (
                <Checkbox
                    checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate')}
                    onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                    aria-label="Select all"
                />
            ),
            cell: ({ row }) => (
                <div onClick={(e) => e.stopPropagation()}>
                    <Checkbox checked={row.getIsSelected()} onCheckedChange={(value) => row.toggleSelected(!!value)} aria-label="Select row" />
                </div>
            ),
            enableHiding: false,
            size: 32,
        },
        {
            id: 'page',
            header: () => <DataTableColumnHeader title="Page" sortKey="id" currentSort={sort} currentDirection={direction} onSort={onSort} />,
            enableHiding: false,
            size: 170,
            cell: ({ row }) => (
                <div className="flex min-w-0 items-center gap-3">
                    <ScanThumb url={row.original.image_url} className="size-9" />
                    <div className="min-w-0">
                        <div className="font-medium tabular-nums">#{row.original.id}</div>
                        <div className="truncate font-mono text-xs text-muted-foreground">scan {row.original.translation_id}</div>
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'split',
            header: 'Split',
            size: 80,
            cell: ({ row }) => <Pill>{row.original.split === 'test' ? 'Test' : 'Train'}</Pill>,
        },
        {
            accessorKey: 'status',
            header: 'Status',
            size: 160,
            cell: ({ row }) => (
                <span className={cn('text-sm', row.original.status === 'done' ? 'text-foreground' : 'text-muted-foreground')}>
                    {STATUS_LABELS[row.original.status]}
                    {row.original.status === 'skipped' && row.original.skip_reason && `: ${SKIP_LABELS[row.original.skip_reason] ?? row.original.skip_reason}`}
                </span>
            ),
        },
        {
            id: 'tags',
            header: 'Tags',
            size: 300,
            enableHiding: false,
            cell: ({ row }) => (
                <div className="flex flex-wrap gap-1.5" onClick={(e) => e.stopPropagation()}>
                    {tagNames.map((tag) => {
                        const on = row.original.tags.includes(tag);
                        return (
                            <button
                                key={tag}
                                type="button"
                                aria-pressed={on}
                                disabled={busy.has(`${row.original.id}:${tag}`)}
                                onClick={() => onToggleTag(row.original, tag)}
                                className={cn(
                                    'h-6 rounded-full border px-2.5 text-xs whitespace-nowrap transition-colors disabled:opacity-50',
                                    on ? 'border-primary bg-primary text-primary-foreground' : 'border-border text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                {TAG_LABELS[tag] ?? tag}
                            </button>
                        );
                    })}
                </div>
            ),
        },
        {
            id: 'cells',
            header: 'Cells',
            size: 90,
            cell: ({ row }) => (
                <span className="tabular-nums" title={row.original.model_cells !== null ? `The phone found ${row.original.model_cells}` : undefined}>
                    {row.original.cells ?? <span className="text-muted-foreground">{row.original.model_cells ?? '—'}</span>}
                </span>
            ),
        },
        {
            id: 'changes',
            header: 'Changes',
            size: 220,
            cell: ({ row }) => <span className="block truncate text-muted-foreground">{changes(row.original)}</span>,
        },
        {
            accessorKey: 'priority',
            header: () => <DataTableColumnHeader title="Priority" sortKey="priority" currentSort={sort} currentDirection={direction} onSort={onSort} />,
            size: 100,
            cell: ({ row }) => <span className="tabular-nums text-muted-foreground">{row.original.priority.toFixed(2)}</span>,
        },
        {
            accessorKey: 'seconds',
            header: () => <DataTableColumnHeader title="Time" sortKey="seconds" currentSort={sort} currentDirection={direction} onSort={onSort} />,
            size: 90,
            cell: ({ row }) => <span className="tabular-nums text-muted-foreground">{duration(row.original.seconds)}</span>,
        },
        {
            accessorKey: 'labeled_at',
            header: () => <DataTableColumnHeader title="Labeled" sortKey="labeled_at" currentSort={sort} currentDirection={direction} onSort={onSort} />,
            size: 190,
            cell: ({ row }) => (
                <span className="tabular-nums text-muted-foreground">
                    {row.original.labeled_at ? `${formatDateTime(row.original.labeled_at)}${row.original.labeled_by ? ` · ${row.original.labeled_by}` : ''}` : '—'}
                </span>
            ),
        },
    ];
}
