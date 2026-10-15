import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';

import { cn } from '@/lib/utils';

export type SortDirection = 'asc' | 'desc';

interface DataTableColumnHeaderProps {
    title: string;
    className?: string;
    /** Omit to render a plain, unsortable header label. */
    sortKey?: string;
    currentSort?: string;
    currentDirection?: SortDirection;
    onSort?: (key: string, direction: SortDirection) => void;
}

export function DataTableColumnHeader({ title, className, sortKey, currentSort, currentDirection, onSort }: DataTableColumnHeaderProps) {
    if (!sortKey || !onSort) {
        return <span className={className}>{title}</span>;
    }

    const isActive = currentSort === sortKey;
    const Icon = isActive ? (currentDirection === 'asc' ? ArrowUp : ArrowDown) : ChevronsUpDown;

    return (
        <button
            type="button"
            className={cn(
                '-mx-1 inline-flex items-center gap-1 rounded px-1 py-0.5 transition-colors hover:text-foreground',
                isActive ? 'text-foreground' : 'text-muted-foreground',
                className,
            )}
            onClick={() => onSort(sortKey, isActive && currentDirection === 'asc' ? 'desc' : 'asc')}
        >
            {title}
            <Icon className="size-3.5" />
        </button>
    );
}
