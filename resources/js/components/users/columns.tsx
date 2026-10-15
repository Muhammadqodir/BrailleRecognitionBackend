import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Copy, MoreHorizontal, ScanText } from 'lucide-react';

import { CountryCell, Empty, formatDate, Pill, PlanBadge, type Plan } from '@/components/badges';
import { DataTableColumnHeader, type SortDirection } from '@/components/data-table/data-table-column-header';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { displayName, initials } from '@/lib/people';

export interface UserRow {
    id: number;
    name: string | null;
    email: string | null;
    rc_user_id: string | null;
    provider: string | null;
    role: string | null;
    platform: string | null;
    country: string | null;
    plan: Plan;
    translations_count: number;
    last_seen_at: string | null;
    created_at: string | null;
}

interface ColumnsOptions {
    sort: string;
    direction: SortDirection;
    onSort: (key: string, direction: SortDirection) => void;
    translationsUrl: string;
}

export function getUserColumns({ sort, direction, onSort, translationsUrl }: ColumnsOptions): ColumnDef<UserRow>[] {
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
            size: 240,
            cell: ({ row }) => {
                const user = row.original;
                const name = displayName(user);

                return (
                    <div className="flex min-w-0 items-center gap-3">
                        <Avatar className="size-8 shrink-0">
                            <AvatarFallback className="text-xs">{initials(name)}</AvatarFallback>
                        </Avatar>
                        <div className="min-w-0">
                            <div className="truncate font-medium text-foreground">{name}</div>
                            <div className="truncate text-xs text-muted-foreground">{user.email ?? `#${user.id}`}</div>
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'provider',
            header: 'Sign-in',
            size: 110,
            cell: ({ row }) => (row.original.provider ? <Pill>{row.original.provider}</Pill> : <Empty />),
        },
        {
            accessorKey: 'country',
            header: 'Country',
            size: 140,
            cell: ({ row }) => <CountryCell code={row.original.country} />,
        },
        {
            accessorKey: 'role',
            header: 'Role',
            // Left at the default size so it takes whatever width is left.
            cell: ({ row }) => <span className="block truncate text-muted-foreground">{row.original.role ?? '—'}</span>,
        },
        {
            accessorKey: 'plan',
            header: 'Plan',
            size: 90,
            cell: ({ row }) => <PlanBadge plan={row.original.plan} />,
        },
        {
            accessorKey: 'translations_count',
            header: header('Scans', 'translations_count'),
            size: 90,
            cell: ({ row }) => <span className="tabular-nums">{row.original.translations_count.toLocaleString()}</span>,
        },
        {
            accessorKey: 'platform',
            header: 'Platform',
            size: 100,
            cell: ({ row }) => (row.original.platform ? <Pill>{row.original.platform}</Pill> : <Empty />),
        },
        {
            accessorKey: 'last_seen_at',
            header: header('Last seen', 'last_seen_at'),
            size: 120,
            cell: ({ row }) => <span className="tabular-nums text-muted-foreground">{formatDate(row.original.last_seen_at, 'Never')}</span>,
        },
        {
            accessorKey: 'created_at',
            header: header('Joined', 'created_at'),
            size: 120,
            cell: ({ row }) => <span className="tabular-nums text-muted-foreground">{formatDate(row.original.created_at)}</span>,
        },
        {
            id: 'actions',
            header: '',
            enableHiding: false,
            size: 40,
            cell: ({ row }) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon-sm">
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link href={`${translationsUrl}?user_id=${row.original.id}`}>
                                <ScanText />
                                View translations
                            </Link>
                        </DropdownMenuItem>
                        {row.original.rc_user_id && (
                            <DropdownMenuItem onSelect={() => navigator.clipboard.writeText(row.original.rc_user_id ?? '')}>
                                <Copy />
                                Copy RevenueCat ID
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];
}
