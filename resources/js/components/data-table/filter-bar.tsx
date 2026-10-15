import type { Table } from '@tanstack/react-table';
import { Download, RotateCcw } from 'lucide-react';
import type { ReactNode } from 'react';

import { DataTableViewOptions } from '@/components/data-table/data-table-view-options';
import { Button } from '@/components/ui/button';

interface FilterBarProps<TData> {
    table: Table<TData>;
    total: number;
    isFiltered: boolean;
    onReset: () => void;
    /** Turns the selected rows into CSV lines; the header row is passed separately. */
    csv?: { filename: string; header: string[]; row: (row: TData) => (string | number | null)[] };
    children: ReactNode;
}

function downloadCsv(filename: string, header: string[], rows: (string | number | null)[][]) {
    const lines = [header, ...rows].map((values) => values.map((value) => `"${String(value ?? '').replace(/"/g, '""')}"`).join(','));
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
}

/** The row under a list page's header: result count, reset, the facet filters, export and column options. */
export function FilterBar<TData>({ table, total, isFiltered, onReset, csv, children }: FilterBarProps<TData>) {
    const selectedRows = table.getFilteredSelectedRowModel().rows;

    return (
        <div className="flex min-w-0 flex-1 items-center gap-3">
            <div className="shrink-0 leading-tight">
                <div className="text-sm font-semibold tabular-nums text-foreground">{total.toLocaleString()}</div>
                <div className="text-[11px] text-muted-foreground">found</div>
            </div>
            {isFiltered && (
                <button
                    type="button"
                    onClick={onReset}
                    className="flex h-8 shrink-0 items-center gap-1.5 rounded-full border border-input px-2.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                >
                    <RotateCcw className="size-3.5" />
                    Reset
                </button>
            )}
            <div className="h-5 w-px shrink-0 bg-border" />
            <div className="flex min-w-0 flex-1 items-center gap-2 overflow-x-auto">{children}</div>
            <div className="ml-auto flex shrink-0 items-center gap-2">
                {csv && selectedRows.length > 0 && (
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 rounded-full"
                        onClick={() => downloadCsv(csv.filename, csv.header, selectedRows.map((row) => csv.row(row.original)))}
                    >
                        <Download />
                        Export {selectedRows.length}
                    </Button>
                )}
                <DataTableViewOptions table={table} />
            </div>
        </div>
    );
}
