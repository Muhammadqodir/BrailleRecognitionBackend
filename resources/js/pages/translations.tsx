import { Head, router, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useMemo, useState } from 'react';

import { DataTable } from '@/components/data-table/data-table';
import type { SortDirection } from '@/components/data-table/data-table-column-header';
import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import { FilterBar } from '@/components/data-table/filter-bar';
import { TableSearch } from '@/components/data-table/table-search';
import { useDataTable } from '@/components/data-table/use-data-table';
import { ENGINE_LABELS, getTranslationColumns, type TranslationRow } from '@/components/translations/columns';
import { TranslationDetailDialog } from '@/components/translations/translation-detail-dialog';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import type { Paginated, SharedProps } from '@/types';

interface TranslationsFilters {
    search: string | null;
    user_id: number | null;
    lang: string[];
    engine: string[];
    rating: string[];
    result: string[];
    sort: string;
    direction: SortDirection;
    per_page: number;
}

interface TranslationsProps {
    translations: Paginated<TranslationRow>;
    langCounts: Record<string, number>;
    engineCounts: Record<string, number>;
    filters: TranslationsFilters;
}

const RATING_OPTIONS = [
    { label: 'Thumbs up', value: 'up' },
    { label: 'Thumbs down', value: 'down' },
    { label: 'Not rated', value: 'none' },
];

const RESULT_OPTIONS = [
    { label: 'Found text', value: 'read' },
    { label: 'Empty', value: 'empty' },
];

export default function Translations({ translations, langCounts, engineCounts, filters }: TranslationsProps) {
    const { routes } = usePage<SharedProps>().props;

    const navigate = (overrides: Partial<Record<string, unknown>>) => {
        router.get(routes.translations, { ...filters, ...overrides }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const columns = useMemo(
        () =>
            getTranslationColumns({
                sort: filters.sort,
                direction: filters.direction,
                onSort: (sort, direction) => navigate({ sort, direction }),
                onUserClick: (userId) => navigate({ user_id: userId, page: undefined }),
            }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [filters],
    );

    const table = useDataTable(columns, translations.data, (row) => String(row.id));
    const [selectedId, setSelectedId] = useState<number | null>(null);

    const isFiltered =
        Boolean(filters.search) ||
        filters.user_id !== null ||
        filters.lang.length > 0 ||
        filters.engine.length > 0 ||
        filters.rating.length > 0 ||
        filters.result.length > 0;

    return (
        <DashboardLayout
            title="Translations"
            description="Every photo the app has read"
            flush
            actions={
                <TableSearch
                    search={filters.search}
                    placeholder="Search the recognised text…"
                    label="Search translations"
                    onSearchChange={(search) => navigate({ search: search || undefined })}
                />
            }
            toolbar={
                <FilterBar
                    table={table}
                    total={translations.total}
                    isFiltered={isFiltered}
                    onReset={() =>
                        router.get(
                            routes.translations,
                            { sort: filters.sort, direction: filters.direction, per_page: filters.per_page },
                            { preserveState: true, preserveScroll: true, replace: true },
                        )
                    }
                    csv={{
                        filename: 'translations.csv',
                        header: ['ID', 'Text', 'Language', 'Read by', 'Rating', 'User ID', 'When'],
                        row: (t) => [t.id, t.text, t.lang, t.engine, t.rating, t.user?.id ?? null, t.created_at],
                    }}
                >
                    {filters.user_id !== null && (
                        <button
                            type="button"
                            onClick={() => navigate({ user_id: undefined })}
                            className="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-full border border-foreground/20 bg-accent px-3 text-sm font-medium whitespace-nowrap"
                        >
                            User #{filters.user_id}
                            <X className="size-3.5 text-muted-foreground" />
                        </button>
                    )}
                    <DataTableFacetedFilter
                        title="Language"
                        selected={filters.lang}
                        onChange={(values) => navigate({ lang: values })}
                        options={Object.entries(langCounts).map(([lang, count]) => ({ label: lang, value: lang, count }))}
                    />
                    <DataTableFacetedFilter
                        title="Read by"
                        selected={filters.engine}
                        onChange={(values) => navigate({ engine: values })}
                        options={(['device', 'server'] as const).map((engine) => ({
                            label: ENGINE_LABELS[engine],
                            value: engine,
                            count: engineCounts[engine] ?? 0,
                        }))}
                    />
                    <DataTableFacetedFilter title="Rating" selected={filters.rating} onChange={(values) => navigate({ rating: values })} options={RATING_OPTIONS} />
                    <DataTableFacetedFilter title="Result" selected={filters.result} onChange={(values) => navigate({ result: values })} options={RESULT_OPTIONS} />
                </FilterBar>
            }
        >
            <Head title="Translations" />

            <DataTable
                table={table}
                columnsCount={columns.length}
                pagination={translations}
                onPerPageChange={(perPage) => navigate({ per_page: perPage })}
                emptyMessage="No translations match these filters."
                onRowClick={(row) => setSelectedId(row.id)}
            />

            <TranslationDetailDialog
                translationId={selectedId}
                detailUrl={selectedId ? `${routes.translations}/${selectedId}` : null}
                onOpenChange={(open) => !open && setSelectedId(null)}
                onUserClick={(userId) => {
                    setSelectedId(null);
                    navigate({ user_id: userId });
                }}
            />
        </DashboardLayout>
    );
}
