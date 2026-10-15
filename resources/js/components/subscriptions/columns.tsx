import type { ColumnDef } from '@tanstack/react-table';

import { CountryCell, Empty, formatDate, Pill } from '@/components/badges';
import { DataTableColumnHeader, type SortDirection } from '@/components/data-table/data-table-column-header';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { displayName, initials } from '@/lib/people';

export interface SubscriptionRow {
    id: number;
    user: { id: number; name: string | null; email: string | null; provider: string | null } | null;
    country: string | null;
    product_id: string | null;
    store: string | null;
    period_type: string | null;
    status: string;
    grants_access: boolean;
    will_renew: boolean;
    environment: string;
    purchased_at: string | null;
    expires_at: string | null;
}

export const STORE_LABELS: Record<string, string> = { app_store: 'App Store', play_store: 'Google Play', promotional: 'Promotional' };

interface ColumnsOptions {
    sort: string;
    direction: SortDirection;
    onSort: (key: string, direction: SortDirection) => void;
}

export function getSubscriptionColumns({ sort, direction, onSort }: ColumnsOptions): ColumnDef<SubscriptionRow>[] {
    const header = (title: string, sortKey: string) => () => (
        <DataTableColumnHeader title={title} sortKey={sortKey} currentSort={sort} currentDirection={direction} onSort={onSort} />
    );

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
                <Checkbox checked={row.getIsSelected()} onCheckedChange={(value) => row.toggleSelected(!!value)} aria-label="Select row" />
            ),
            enableHiding: false,
            size: 32,
        },
        {
            id: 'user',
            header: 'User',
            enableHiding: false,
            size: 220,
            cell: ({ row }) => {
                const user = row.original.user;

                if (!user) {
                    return <Empty />;
                }

                return (
                    <div className="flex min-w-0 items-center gap-3">
                        <Avatar className="size-8 shrink-0">
                            <AvatarFallback className="text-xs">{initials(displayName(user))}</AvatarFallback>
                        </Avatar>
                        <div className="min-w-0">
                            <div className="truncate font-medium text-foreground">{displayName(user)}</div>
                            <div className="truncate text-xs text-muted-foreground">{user.email ?? `#${user.id}`}</div>
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'country',
            header: 'Country',
            size: 130,
            cell: ({ row }) => <CountryCell code={row.original.country} />,
        },
        {
            accessorKey: 'product_id',
            header: 'Product',
            // Default size: takes whatever width is left
            cell: ({ row }) => <span className="block truncate">{row.original.product_id ?? '—'}</span>,
        },
        {
            accessorKey: 'store',
            header: 'Store',
            size: 100,
            cell: ({ row }) => <span className="text-muted-foreground">{STORE_LABELS[row.original.store ?? ''] ?? row.original.store ?? '—'}</span>,
        },
        {
            accessorKey: 'period_type',
            header: 'Period',
            size: 80,
            cell: ({ row }) => (row.original.period_type ? <Pill>{row.original.period_type.toLowerCase()}</Pill> : <Empty />),
        },
        {
            accessorKey: 'status',
            header: 'Status',
            size: 130,
            cell: ({ row }) => (
                <div className="flex items-center gap-1.5">
                    <Badge variant={row.original.grants_access ? 'default' : 'outline'}>{row.original.status.replace('_', ' ')}</Badge>
                    {row.original.environment !== 'PRODUCTION' && <Pill>{row.original.environment.toLowerCase()}</Pill>}
                </div>
            ),
        },
        {
            accessorKey: 'will_renew',
            header: 'Renews',
            size: 70,
            cell: ({ row }) => <span className="text-muted-foreground">{row.original.will_renew ? 'Yes' : 'No'}</span>,
        },
        {
            accessorKey: 'purchased_at',
            header: header('Purchased', 'purchased_at'),
            size: 100,
            cell: ({ row }) => <span className="tabular-nums text-muted-foreground">{formatDate(row.original.purchased_at)}</span>,
        },
        {
            accessorKey: 'expires_at',
            header: header('Expires', 'expires_at'),
            size: 100,
            cell: ({ row }) => <span className="tabular-nums text-muted-foreground">{formatDate(row.original.expires_at)}</span>,
        },
    ];
}
