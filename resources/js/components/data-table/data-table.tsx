import { flexRender, type RowData, type Table as TableInstance } from '@tanstack/react-table';
import { useLayoutEffect, useRef, useState } from 'react';

import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

declare module '@tanstack/react-table' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData extends RowData, TValue> {
        /**
         * When the table is too narrow for every column, this one is hidden:
         * 1 goes first, then 2, and so on. Columns without it always show.
         */
        dropOrder?: number;
    }
}

/** Cell columns whose own controls (checkbox, dropdown menu) must not trigger the row click. */
const NON_NAVIGATING_COLUMN_IDS = new Set(['select', 'actions']);

/** Column `size` 150 is TanStack's default, i.e. "no width given": it takes what is left. */
const NO_WIDTH = 150;
/** What a column with no width needs at least. */
const FLEX_MIN = 160;
/** Checkbox and icon columns keep their width. */
const FIXED_UP_TO = 48;
/** Below this share of their set width, columns get too cramped and the least important ones go. */
const MIN_SCALE = 0.8;

interface Fit {
    hidden: Set<string>;
    width: (id: string, size: number) => number | undefined;
}

/**
 * Fits the columns to the table's width, so the page never scrolls sideways:
 * set widths shrink in proportion, and when that would squeeze them below
 * MIN_SCALE, columns with a `dropOrder` are hidden (their data stays in the
 * row's detail dialog).
 */
function fitColumns(columns: { id: string; size: number; dropOrder?: number }[], available: number): Fit {
    const hidden = new Set<string>();
    const scale = () => {
        const shown = columns.filter((column) => !hidden.has(column.id));
        const fixed = shown.filter((column) => column.size <= FIXED_UP_TO).reduce((sum, column) => sum + column.size, 0);
        const flexible = shown
            .filter((column) => column.size > FIXED_UP_TO)
            .reduce((sum, column) => sum + (column.size === NO_WIDTH ? FLEX_MIN : column.size), 0);

        return flexible > 0 ? (available - fixed) / flexible : 1;
    };

    if (available > 0) {
        const droppable = columns.filter((column) => column.dropOrder !== undefined).sort((a, b) => (a.dropOrder ?? 0) - (b.dropOrder ?? 0));
        while (scale() < MIN_SCALE && droppable.length > 0) {
            hidden.add(droppable.shift()!.id);
        }
    }

    const factor = available > 0 ? Math.min(1, scale()) : 1;

    return {
        hidden,
        width: (_id, size) => (size === NO_WIDTH ? undefined : size <= FIXED_UP_TO ? size : Math.floor(size * factor)),
    };
}

interface DataTableProps<TData> {
    table: TableInstance<TData>;
    columnsCount: number;
    pagination: Pick<Paginated<unknown>, 'current_page' | 'last_page' | 'per_page' | 'prev_page_url' | 'next_page_url'>;
    onPerPageChange: (perPage: number) => void;
    emptyMessage: string;
    onRowClick?: (row: TData) => void;
}

export function DataTable<TData>({ table, columnsCount, pagination, onPerPageChange, emptyMessage, onRowClick }: DataTableProps<TData>) {
    const rows = table.getRowModel().rows;
    const wrapper = useRef<HTMLDivElement>(null);
    const [available, setAvailable] = useState(0);

    useLayoutEffect(() => {
        // The scrolling box, not the wrapper: its width leaves out the vertical scrollbar.
        const element = wrapper.current?.querySelector<HTMLElement>('[data-slot=table-container]');
        if (!element) {
            return;
        }
        const measure = () => setAvailable(element.clientWidth);
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    const fit = fitColumns(
        table.getVisibleLeafColumns().map((column) => ({ id: column.id, size: column.getSize(), dropOrder: column.columnDef.meta?.dropOrder })),
        available,
    );

    return (
        <div ref={wrapper} className="flex min-h-0 flex-1 flex-col gap-3">
            <Table className="table-fixed border-separate border-spacing-0" containerClassName="min-h-0 flex-1 overflow-auto">
                <TableHeader>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRow key={headerGroup.id} className="hover:bg-transparent">
                            {headerGroup.headers.filter((header) => !fit.hidden.has(header.column.id)).map((header) => (
                                <TableHead
                                    key={header.id}
                                    className="sticky top-0 z-10 h-9 border-b bg-background text-xs font-bold text-muted-foreground"
                                    style={{ width: fit.width(header.column.id, header.getSize()) }}
                                >
                                    {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                </TableHead>
                            ))}
                        </TableRow>
                    ))}
                </TableHeader>
                <TableBody className="[&_tr:last-child_td]:border-b-0">
                    {rows.length === 0 ? (
                        <TableRow>
                            <TableCell colSpan={columnsCount - fit.hidden.size} className="h-24 text-center text-sm text-muted-foreground">
                                {emptyMessage}
                            </TableCell>
                        </TableRow>
                    ) : (
                        rows.map((row) => (
                            <TableRow
                                key={row.id}
                                data-state={row.getIsSelected() && 'selected'}
                                onClick={onRowClick ? () => onRowClick(row.original) : undefined}
                                className={cn(onRowClick && 'cursor-pointer')}
                            >
                                {row.getVisibleCells().filter((cell) => !fit.hidden.has(cell.column.id)).map((cell) => (
                                    <TableCell
                                        key={cell.id}
                                        className={cn('border-b border-border/60 py-2.5 text-[13px]', !NON_NAVIGATING_COLUMN_IDS.has(cell.column.id) && 'overflow-hidden')}
                                        onClick={NON_NAVIGATING_COLUMN_IDS.has(cell.column.id) ? (event) => event.stopPropagation() : undefined}
                                    >
                                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))
                    )}
                </TableBody>
            </Table>
            {rows.length > 0 && (
                <div className="shrink-0">
                    <DataTablePagination table={table} pagination={pagination} onPerPageChange={onPerPageChange} />
                </div>
            )}
        </div>
    );
}
