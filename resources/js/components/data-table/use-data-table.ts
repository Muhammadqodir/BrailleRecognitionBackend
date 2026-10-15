import { type ColumnDef, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { useState } from 'react';

/** Row selection and column visibility are the only client-side state a server-driven table needs. */
export function useDataTable<TData>(columns: ColumnDef<TData, unknown>[], data: TData[], getRowId: (row: TData) => string) {
    const [rowSelection, setRowSelection] = useState({});
    const [columnVisibility, setColumnVisibility] = useState({});

    return useReactTable({
        data,
        columns,
        state: { rowSelection, columnVisibility },
        enableRowSelection: true,
        onRowSelectionChange: setRowSelection,
        onColumnVisibilityChange: setColumnVisibility,
        getRowId,
        getCoreRowModel: getCoreRowModel(),
    });
}
