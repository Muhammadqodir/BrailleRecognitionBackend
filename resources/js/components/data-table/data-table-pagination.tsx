import { Link } from '@inertiajs/react';
import type { Table } from '@tanstack/react-table';
import { ChevronLeft, ChevronRight } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Paginated } from '@/types';

const PAGE_SIZES = [10, 20, 30, 50];

interface DataTablePaginationProps<TData> {
    table: Table<TData>;
    pagination: Pick<Paginated<unknown>, 'current_page' | 'last_page' | 'per_page' | 'prev_page_url' | 'next_page_url'>;
    onPerPageChange: (perPage: number) => void;
}

export function DataTablePagination<TData>({ table, pagination, onPerPageChange }: DataTablePaginationProps<TData>) {
    const selected = table.getFilteredSelectedRowModel().rows.length;
    const onPage = table.getFilteredRowModel().rows.length;

    return (
        <div className="flex items-center justify-between border-t px-4 pt-3 pb-4 sm:px-6">
            <div className="text-xs text-muted-foreground">
                {selected} of {onPage} row(s) selected.
            </div>
            <div className="flex items-center gap-6 lg:gap-8">
                <div className="flex items-center gap-2">
                    <p className="text-xs font-medium text-muted-foreground">Rows per page</p>
                    <Select value={`${pagination.per_page}`} onValueChange={(value) => onPerPageChange(Number(value))}>
                        <SelectTrigger className="h-8 w-[70px]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent side="top">
                            {PAGE_SIZES.map((size) => (
                                <SelectItem key={size} value={`${size}`}>
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="text-sm font-medium">
                    Page {pagination.current_page} of {pagination.last_page || 1}
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline" size="icon" disabled={!pagination.prev_page_url} asChild={!!pagination.prev_page_url}>
                        {pagination.prev_page_url ? (
                            <Link href={pagination.prev_page_url} preserveScroll preserveState>
                                <ChevronLeft />
                            </Link>
                        ) : (
                            <ChevronLeft />
                        )}
                    </Button>
                    <Button variant="outline" size="icon" disabled={!pagination.next_page_url} asChild={!!pagination.next_page_url}>
                        {pagination.next_page_url ? (
                            <Link href={pagination.next_page_url} preserveScroll preserveState>
                                <ChevronRight />
                            </Link>
                        ) : (
                            <ChevronRight />
                        )}
                    </Button>
                </div>
            </div>
        </div>
    );
}
