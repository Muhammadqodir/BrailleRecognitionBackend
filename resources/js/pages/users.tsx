import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { PLAN_LABELS, type Plan } from '@/components/badges';
import { DataTable } from '@/components/data-table/data-table';
import type { SortDirection } from '@/components/data-table/data-table-column-header';
import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import { FilterBar } from '@/components/data-table/filter-bar';
import { TableSearch } from '@/components/data-table/table-search';
import { useDataTable } from '@/components/data-table/use-data-table';
import { getUserColumns, type UserRow } from '@/components/users/columns';
import { UserDetailDialog } from '@/components/users/user-detail-dialog';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import type { Paginated, SharedProps } from '@/types';

interface UsersFilters {
    search: string | null;
    provider: string[];
    plan: Plan[];
    role: string[];
    sort: string;
    direction: SortDirection;
    per_page: number;
}

interface UsersProps {
    users: Paginated<UserRow>;
    providerCounts: Record<string, number>;
    roleCounts: Record<string, number>;
    planCounts: Record<Plan, number>;
    filters: UsersFilters;
}

const toOptions = (counts: Record<string, number>) => Object.entries(counts).map(([value, count]) => ({ label: value, value, count }));

export default function Users({ users, providerCounts, roleCounts, planCounts, filters }: UsersProps) {
    const { routes } = usePage<SharedProps>().props;

    const navigate = (overrides: Partial<Record<string, unknown>>) => {
        router.get(routes.users, { ...filters, ...overrides }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const columns = useMemo(
        () =>
            getUserColumns({
                sort: filters.sort,
                direction: filters.direction,
                onSort: (sort, direction) => navigate({ sort, direction }),
                translationsUrl: routes.translations,
            }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [filters.sort, filters.direction],
    );

    const table = useDataTable(columns, users.data, (row) => String(row.id));
    const [selectedUserId, setSelectedUserId] = useState<number | null>(null);

    const isFiltered = Boolean(filters.search) || filters.provider.length > 0 || filters.plan.length > 0 || filters.role.length > 0;

    return (
        <DashboardLayout
            title="Users"
            description="Browse and search app accounts"
            flush
            actions={
                <TableSearch
                    search={filters.search}
                    placeholder="Search by name, email or ID…"
                    label="Search users"
                    onSearchChange={(search) => navigate({ search: search || undefined })}
                />
            }
            toolbar={
                <FilterBar
                    table={table}
                    total={users.total}
                    isFiltered={isFiltered}
                    onReset={() =>
                        router.get(
                            routes.users,
                            { sort: filters.sort, direction: filters.direction, per_page: filters.per_page },
                            { preserveState: true, preserveScroll: true, replace: true },
                        )
                    }
                    csv={{
                        filename: 'users.csv',
                        header: ['ID', 'Name', 'Email', 'Sign-in', 'Role', 'Plan', 'Scans', 'Platform', 'Last seen', 'Joined'],
                        row: (user) => [
                            user.id,
                            user.name,
                            user.email,
                            user.provider,
                            user.role,
                            user.plan,
                            user.translations_count,
                            user.platform,
                            user.last_seen_at,
                            user.created_at,
                        ],
                    }}
                >
                    <DataTableFacetedFilter
                        title="Plan"
                        selected={filters.plan}
                        onChange={(values) => navigate({ plan: values })}
                        options={(Object.keys(PLAN_LABELS) as Plan[]).map((plan) => ({ label: PLAN_LABELS[plan], value: plan, count: planCounts[plan] }))}
                    />
                    <DataTableFacetedFilter
                        title="Sign-in"
                        selected={filters.provider}
                        onChange={(values) => navigate({ provider: values })}
                        options={toOptions(providerCounts)}
                    />
                    <DataTableFacetedFilter title="Role" selected={filters.role} onChange={(values) => navigate({ role: values })} options={toOptions(roleCounts)} />
                </FilterBar>
            }
        >
            <Head title="Users" />

            <DataTable
                table={table}
                columnsCount={columns.length}
                pagination={users}
                onPerPageChange={(perPage) => navigate({ per_page: perPage })}
                emptyMessage="No users match these filters."
                onRowClick={(row) => setSelectedUserId(row.id)}
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
