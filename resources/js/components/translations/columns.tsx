import type { ColumnDef } from '@tanstack/react-table';
import { ImageIcon, MoreHorizontal, SlidersHorizontal, User } from 'lucide-react';

import { formatDateTime, Pill, RatingMark } from '@/components/badges';
import { DataTableColumnHeader, type SortDirection } from '@/components/data-table/data-table-column-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { ScanThumb } from '@/components/users/user-detail-dialog';
import { displayName } from '@/lib/people';

export interface TranslationUser {
    id: number;
    name: string | null;
    email: string | null;
    provider: string | null;
}

export interface TranslationRow {
    id: number;
    text: string;
    image_url: string | null;
    lang: string;
    engine: 'device' | 'server';
    rating: number | null;
    is_fav: boolean;
    /** Where the scan is in the labeling queue, null when it is not there. */
    label_status: 'todo' | 'done' | 'skipped' | null;
    /** A phone scan with its photo kept on the server: it can be opened in the labeling editor. */
    can_tune: boolean;
    user: TranslationUser | null;
    created_at: string;
}

export const ENGINE_LABELS: Record<TranslationRow['engine'], string> = { device: 'Phone', server: 'OCR server' };

interface ColumnsOptions {
    sort: string;
    direction: SortDirection;
    onSort: (key: string, direction: SortDirection) => void;
    onUserClick: (userId: number) => void;
    tuneUrl: (translationId: number) => string;
    /** Tune opens the labeling page in a new tab. */
    onTuneOpened?: () => void;
}

export const TUNE_HINT = 'Correct the cells on the labeling page; saving adds the scan to the dataset';

export function getTranslationColumns({ sort, direction, onSort, onUserClick, tuneUrl, onTuneOpened }: ColumnsOptions): ColumnDef<TranslationRow>[] {
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
            id: 'result',
            header: 'Result',
            enableHiding: false,
            size: 344,
            cell: ({ row }) => (
                <div className="flex min-w-0 items-center gap-3">
                    <ScanThumb url={row.original.image_url} className="size-9" />
                    <div className="min-w-0">
                        <div className="truncate font-medium text-foreground">
                            {row.original.text || <span className="font-normal text-muted-foreground italic">No text found</span>}
                        </div>
                        <div className="truncate text-xs text-muted-foreground">
                            <span className="font-mono">#{row.original.id}</span>
                            {row.original.label_status === 'done' && <span> · In dataset</span>}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'lang',
            header: () => <DataTableColumnHeader title="Language" sortKey="lang" currentSort={sort} currentDirection={direction} onSort={onSort} />,
            size: 110,
            cell: ({ row }) => <Pill>{row.original.lang}</Pill>,
        },
        {
            accessorKey: 'engine',
            meta: { dropOrder: 1 },
            header: 'Read by',
            size: 110,
            cell: ({ row }) => <span className="text-muted-foreground">{ENGINE_LABELS[row.original.engine]}</span>,
        },
        {
            accessorKey: 'rating',
            meta: { dropOrder: 2 },
            header: 'Rating',
            size: 80,
            cell: ({ row }) => <RatingMark rating={row.original.rating} />,
        },
        {
            id: 'user',
            meta: { dropOrder: 3 },
            header: 'User',
            size: 200,
            cell: ({ row }) =>
                row.original.user ? (
                    <span className="block truncate text-muted-foreground">{displayName(row.original.user)}</span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            accessorKey: 'created_at',
            header: () => <DataTableColumnHeader title="When" sortKey="created_at" currentSort={sort} currentDirection={direction} onSort={onSort} />,
            size: 170,
            cell: ({ row }) => <span className="tabular-nums text-muted-foreground">{formatDateTime(row.original.created_at)}</span>,
        },
        {
            id: 'actions',
            header: '',
            enableHiding: false,
            size: 76,
            cell: ({ row }) => (
                <div className="flex items-center justify-end gap-0.5">
                    {row.original.can_tune && (
                        <Button variant="ghost" size="icon-sm" asChild>
                            <a href={tuneUrl(row.original.id)} target="_blank" rel="noopener" onClick={onTuneOpened} title={`Tune. ${TUNE_HINT}. Opens in a new tab.`} aria-label="Tune (opens in a new tab)">
                                <SlidersHorizontal />
                            </a>
                        </Button>
                    )}
                    {row.original.user && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="icon-sm">
                                    <MoreHorizontal />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onSelect={() => row.original.user && onUserClick(row.original.user.id)}>
                                    <User />
                                    Only this user's translations
                                </DropdownMenuItem>
                                {row.original.image_url && (
                                    <DropdownMenuItem asChild>
                                        <a href={row.original.image_url} target="_blank" rel="noreferrer">
                                            <ImageIcon />
                                            Open photo
                                        </a>
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            ),
        },
    ];
}
