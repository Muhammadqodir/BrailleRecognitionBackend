import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Input } from '@/components/ui/input';

interface TableSearchProps {
    search: string | null;
    placeholder: string;
    label: string;
    onSearchChange: (value: string) => void;
}

/** The header search box: debounced, so the server is asked once the typing stops. */
export function TableSearch({ search, placeholder, label, onSearchChange }: TableSearchProps) {
    const [searchValue, setSearchValue] = useState(search ?? '');

    useEffect(() => setSearchValue(search ?? ''), [search]);

    useEffect(() => {
        const handle = setTimeout(() => {
            if (searchValue !== (search ?? '')) {
                onSearchChange(searchValue);
            }
        }, 300);

        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchValue]);

    return (
        <div className="relative w-64">
            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input
                value={searchValue}
                onChange={(event) => setSearchValue(event.target.value)}
                placeholder={placeholder}
                aria-label={label}
                className="h-8 pl-8 text-xs"
            />
        </div>
    );
}
