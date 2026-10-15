import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { DataTable } from '@/components/data-table/data-table';
import type { SortDirection } from '@/components/data-table/data-table-column-header';
import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import { FilterBar } from '@/components/data-table/filter-bar';
import { TableSearch } from '@/components/data-table/table-search';
import { useDataTable } from '@/components/data-table/use-data-table';
import { getSubscriptionColumns, STORE_LABELS, type SubscriptionRow } from '@/components/subscriptions/columns';
import { UserDetailDialog } from '@/components/users/user-detail-dialog';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import type { Paginated, SharedProps } from '@/types';

type Facet = 'status' | 'store' | 'period_type' | 'environment' | 'product_id';

type SubscriptionsFilters = Record<Facet, string[]> & {
    search: string | null;
    sort: string;
    direction: SortDirection;
    per_page: number;
};

interface SubscriptionsProps {
    subscriptions: Paginated<SubscriptionRow>;
    facetCounts: Record<Facet, Record<string, number>>;
    filters: SubscriptionsFilters;
}

const FACETS: { key: Facet; title: string; label?: (value: string) => string }[] = [
    { key: 'status', title: 'Status', label: (value) => value.replace('_', ' ') },
    { key: 'store', title: 'Store', label: (value) => STORE_LABELS[value] ?? value },
    { key: 'period_type', title: 'Period', label: (value) => value.toLowerCase() },
    { key: 'product_id', title: 'Product' },
    { key: 'environment', title: 'Environment', label: (value) => value.toLowerCase() },
];

export default function Subscriptions({ subscriptions, facetCounts, filters }: SubscriptionsProps) {
    const { routes } = usePage<SharedProps>().props;

    const navigate = (overrides: Partial<Record<string, unknown>>) => {
        router.get(routes.subscriptions, { ...filters, ...overrides }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const columns = useMemo(
        () =>
            getSubscriptionColumns({
                sort: filters.sort,
                direction: filters.direction,
                onSort: (sort, direction) => navigate({ sort, direction }),
            }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [filters.sort, filters.direction],
    );

    const table = useDataTable(columns, subscriptions.data, (row) => String(row.id));
    const [selectedUserId, setSelectedUserId] = useState<number | null>(null);

    const isFiltered = Boolean(filters.search) || FACETS.some((facet) => filters[facet.key].length > 0);

    return (
        <DashboardLayout
            title="Subscriptions"
            description="Every trial and purchase, from the RevenueCat webhook"
            flush
            actions={
                <TableSearch
                    search={filters.search}
                    placeholder="Search by email or RevenueCat ID…"
                    label="Search subscriptions"
                    onSearchChange={(search) => navigate({ search: search || undefined })}
                />
            }
            toolbar={
                <FilterBar
                    table={table}
                    total={subscriptions.total}
                    isFiltered={isFiltered}
                    onReset={() =>
                        router.get(
                            routes.subscriptions,
                            { sort: filters.sort, direction: filters.direction, per_page: filters.per_page },
                            { preserveState: true, preserveScroll: true, replace: true },
                        )
                    }
                    csv={{
                        filename: 'subscriptions.csv',
                        header: ['ID', 'User ID', 'Email', 'Product', 'Store', 'Period', 'Status', 'Environment', 'Renews', 'Purchased', 'Expires'],
                        row: (s) => [
                            s.id,
                            s.user?.id ?? null,
                            s.user?.email ?? null,
                            s.product_id,
                            s.store,
                            s.period_type,
                            s.status,
                            s.environment,
                            s.will_renew ? 'yes' : 'no',
                            s.purchased_at,
                            s.expires_at,
                        ],
                    }}
                >
                    {FACETS.map((facet) => (
                        <DataTableFacetedFilter
                            key={facet.key}
                            title={facet.title}
                            selected={filters[facet.key]}
                            onChange={(values) => navigate({ [facet.key]: values })}
                            options={Object.entries(facetCounts[facet.key]).map(([value, count]) => ({
                                label: facet.label ? facet.label(value) : value,
                                value,
                                count,
                            }))}
                        />
                    ))}
                </FilterBar>
            }
        >
            <Head title="Subscriptions" />

            <DataTable
                table={table}
                columnsCount={columns.length}
                pagination={subscriptions}
                onPerPageChange={(perPage) => navigate({ per_page: perPage })}
                emptyMessage="No subscriptions match these filters."
                onRowClick={(row) => row.user && setSelectedUserId(row.user.id)}
            />

            <UserDetailDialog
                userId={selectedUserId}
                detailUrl={selectedUserId ? `${routes.users}/${selectedUserId}` : null}
                translationsUrl={routes.translations}
                onOpenChange={(open) => !open && setSelectedUserId(null)}
            />
        </DashboardLayout>
    );
}
