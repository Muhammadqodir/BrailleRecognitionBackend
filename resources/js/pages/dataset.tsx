import { Head, router, usePage } from '@inertiajs/react';
import { ChevronDown, Tag } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { DataTable } from '@/components/data-table/data-table';
import type { SortDirection } from '@/components/data-table/data-table-column-header';
import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import { FilterBar } from '@/components/data-table/filter-bar';
import { TableSearch } from '@/components/data-table/table-search';
import { useDataTable } from '@/components/data-table/use-data-table';
import { getDatasetColumns, STATUS_LABELS, TAG_LABELS, type DatasetRow } from '@/components/dataset/columns';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import { send, SignedOut } from '@/lib/http';
import type { Paginated, SharedProps } from '@/types';

interface DatasetFilters {
    search: number | null;
    split: string[];
    status: string[];
    tag: string[];
    sort: string;
    direction: SortDirection;
    per_page: number;
}

interface DatasetProps {
    pages: Paginated<DatasetRow>;
    splitCounts: Record<string, number>;
    statusCounts: Record<string, number>;
    tagCounts: Record<string, number>;
    tagNames: string[];
    filters: DatasetFilters;
}

export default function Dataset({ pages, splitCounts, statusCounts, tagCounts, tagNames, filters }: DatasetProps) {
    const { routes } = usePage<SharedProps>().props;
    const [rows, setRows] = useState(pages.data);
    const [busy, setBusy] = useState<Set<string>>(new Set());
    const [error, setError] = useState<string | null>(null);

    useEffect(() => setRows(pages.data), [pages.data]);

    const navigate = (overrides: Partial<Record<string, unknown>>) => {
        router.get(routes.dataset, { ...filters, ...overrides }, { preserveState: true, preserveScroll: true, replace: true });
    };

    /** Sets a tag on pages, showing it at once and putting it back if the server refuses. */
    const setTag = async (ids: number[], tag: string, on: boolean) => {
        const keys = ids.map((id) => `${id}:${tag}`);
        const before = rows;
        setBusy((b) => new Set([...b, ...keys]));
        setRows((rs) => rs.map((r) => (ids.includes(r.id) ? { ...r, tags: on ? [...new Set([...r.tags, tag])] : r.tags.filter((t) => t !== tag) } : r)));
        try {
            const result = await send<{ data: Record<string, string[]> }>(routes.datasetTags, 'PATCH', { ids, tag, on });
            setRows((rs) => rs.map((r) => (result.data[r.id] ? { ...r, tags: result.data[r.id] } : r)));
            setError(null);
        } catch (e) {
            setRows(before);
            setError(e instanceof SignedOut ? 'You were signed out. Sign in again and retry.' : (e as Error).message);
        } finally {
            setBusy((b) => new Set([...b].filter((k) => !keys.includes(k))));
        }
    };

    const columns = useMemo(
        () =>
            getDatasetColumns({
                sort: filters.sort,
                direction: filters.direction,
                onSort: (sort, direction) => navigate({ sort, direction }),
                tagNames,
                busy,
                onToggleTag: (row, tag) => void setTag([row.id], tag, !row.tags.includes(tag)),
            }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [filters, tagNames, busy, rows],
    );

    const table = useDataTable(columns, rows, (row) => String(row.id));
    const selected = table.getSelectedRowModel().rows.map((r) => r.original.id);
    const isFiltered = filters.search !== null || filters.split.length > 0 || filters.status.length > 0 || filters.tag.length > 0;

    return (
        <DashboardLayout
            title="Dataset"
            description="Every page in the labeling queue"
            flush
            actions={
                <TableSearch
                    search={filters.search ? String(filters.search) : null}
                    placeholder="Page or scan number…"
                    label="Find a page"
                    onSearchChange={(search) => navigate({ search: search || undefined, page: undefined })}
                />
            }
            toolbar={
                <FilterBar
                    table={table}
                    total={pages.total}
                    isFiltered={isFiltered}
                    onReset={() =>
                        router.get(
                            routes.dataset,
                            { sort: filters.sort, direction: filters.direction, per_page: filters.per_page },
                            { preserveState: true, preserveScroll: true, replace: true },
                        )
                    }
                    csv={{
                        filename: 'dataset.csv',
                        header: ['Page', 'Scan', 'Split', 'Status', 'Skip reason', 'Tags', 'Cells', 'Phone cells', 'Seconds', 'Labeled at'],
                        row: (r) => [r.id, r.translation_id, r.split, r.status, r.skip_reason, r.tags.join(' '), r.cells, r.model_cells, r.seconds, r.labeled_at],
                    }}
                >
                    <DataTableFacetedFilter
                        title="Status"
                        selected={filters.status}
                        onChange={(values) => navigate({ status: values, page: undefined })}
                        options={(['done', 'todo', 'skipped'] as const).map((s) => ({ label: STATUS_LABELS[s], value: s, count: statusCounts[s] ?? 0 }))}
                    />
                    <DataTableFacetedFilter
                        title="Split"
                        selected={filters.split}
                        onChange={(values) => navigate({ split: values, page: undefined })}
                        options={[
                            { label: 'Train', value: 'train', count: splitCounts.train ?? 0 },
                            { label: 'Test', value: 'test', count: splitCounts.test ?? 0 },
                        ]}
                    />
                    <DataTableFacetedFilter
                        title="Tags"
                        selected={filters.tag}
                        onChange={(values) => navigate({ tag: values, page: undefined })}
                        options={[
                            ...tagNames.map((t) => ({ label: TAG_LABELS[t] ?? t, value: t, count: tagCounts[t] ?? 0 })),
                            { label: 'No tags', value: 'none' },
                        ]}
                    />
                    {selected.length > 0 && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm" className="rounded-full">
                                    <Tag />
                                    Tag {selected.length} selected
                                    <ChevronDown />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start">
                                {tagNames.map((t) => (
                                    <DropdownMenuItem key={`on-${t}`} onSelect={() => void setTag(selected, t, true)}>
                                        Mark {TAG_LABELS[t] ?? t}
                                    </DropdownMenuItem>
                                ))}
                                <DropdownMenuSeparator />
                                {tagNames.map((t) => (
                                    <DropdownMenuItem key={`off-${t}`} onSelect={() => void setTag(selected, t, false)}>
                                        Clear {TAG_LABELS[t] ?? t}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                    {error && <span className="text-sm text-destructive">{error}</span>}
                </FilterBar>
            }
        >
            <Head title="Dataset" />

            <DataTable
                table={table}
                columnsCount={columns.length}
                pagination={pages}
                onPerPageChange={(perPage) => navigate({ per_page: perPage })}
                emptyMessage="No pages match these filters."
                onRowClick={(row) => router.visit(`${routes.labeling}?page=${row.id}`)}
            />
        </DashboardLayout>
    );
}
