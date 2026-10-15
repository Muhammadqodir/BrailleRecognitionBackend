import { Check, ChevronDown } from 'lucide-react';

import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export interface FacetedFilterOption {
    label: string;
    value: string;
    count?: number;
}

interface DataTableFacetedFilterProps {
    title: string;
    options: FacetedFilterOption[];
    selected: string[];
    onChange: (values: string[]) => void;
}

export function DataTableFacetedFilter({ title, options, selected, onChange }: DataTableFacetedFilterProps) {
    const selectedSet = new Set(selected);
    const selectedLabel =
        selectedSet.size === 0
            ? title
            : selectedSet.size === 1
              ? (options.find((option) => selectedSet.has(option.value))?.label ?? title)
              : `${title} (${selectedSet.size})`;

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        'inline-flex h-8 shrink-0 items-center gap-1.5 rounded-full border px-3 text-sm whitespace-nowrap transition-colors',
                        selectedSet.size > 0
                            ? 'border-foreground/20 bg-accent font-medium text-foreground'
                            : 'border-input text-muted-foreground hover:bg-muted hover:text-foreground',
                    )}
                >
                    {selectedLabel}
                    <ChevronDown className="size-3.5 text-muted-foreground" />
                </button>
            </PopoverTrigger>
            <PopoverContent className="w-52 p-0" align="start">
                <Command>
                    <CommandInput placeholder={title} />
                    <CommandList>
                        <CommandEmpty>No results found.</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => {
                                const isSelected = selectedSet.has(option.value);

                                return (
                                    <CommandItem
                                        key={option.value}
                                        onSelect={() => {
                                            const next = new Set(selectedSet);
                                            if (isSelected) {
                                                next.delete(option.value);
                                            } else {
                                                next.add(option.value);
                                            }
                                            onChange(Array.from(next));
                                        }}
                                    >
                                        <div
                                            className={cn(
                                                'flex size-4 items-center justify-center rounded-sm border border-primary',
                                                isSelected ? 'bg-primary text-primary-foreground' : 'opacity-50 [&_svg]:invisible',
                                            )}
                                        >
                                            <Check className="size-3.5" />
                                        </div>
                                        <span>{option.label}</span>
                                        {option.count !== undefined && (
                                            <span className="ml-auto flex size-4 items-center justify-center font-mono text-xs text-muted-foreground">
                                                {option.count}
                                            </span>
                                        )}
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                        {selectedSet.size > 0 && (
                            <>
                                <CommandSeparator />
                                <CommandGroup>
                                    <CommandItem onSelect={() => onChange([])} className="justify-center text-center">
                                        Clear filters
                                    </CommandItem>
                                </CommandGroup>
                            </>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
