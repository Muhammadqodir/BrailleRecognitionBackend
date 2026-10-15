import { flexRender, type Table as TableInstance } from '@tanstack/react-table';

import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

/** Cell columns whose own controls (checkbox, dropdown menu) must not trigger the row click. */
const NON_NAVIGATING_COLUMN_IDS = new Set(['select', 'actions']);

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

    return (
        <div className="flex min-h-0 flex-1 flex-col gap-3">
            <Table className="table-fixed border-separate border-spacing-0" containerClassName="min-h-0 flex-1 overflow-auto">
                <TableHeader>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRow key={headerGroup.id} className="hover:bg-transparent">
                            {headerGroup.headers.map((header) => (
                                <TableHead
                                    key={header.id}
                                    className="sticky top-0 z-10 h-9 border-b bg-background text-xs font-bold text-muted-foreground"
                                    style={{ width: header.getSize() !== 150 ? header.getSize() : undefined }}
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
                            <TableCell colSpan={columnsCount} className="h-24 text-center text-sm text-muted-foreground">
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
                                {row.getVisibleCells().map((cell) => (
                                    <TableCell
                                        key={cell.id}
                                        className="border-b border-border/60 py-2.5 text-[13px]"
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
