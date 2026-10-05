import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

export interface PickedEntry {
    id: number;
    title: string;
    collection: string;
}

interface Props {
    collections?: string[];
    multiple?: boolean;
    value: PickedEntry[];
    onChange: (entries: PickedEntry[]) => void;
    placeholder?: string;
}

/**
 * Searchable entry picker backed by `GET {admin}/api/entries`.
 */
export default function EntryPicker({ collections = [], multiple = false, value, onChange, placeholder = 'Pick entry…' }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const [open, setOpen] = React.useState(false);
    const [query, setQuery] = React.useState('');
    const [results, setResults] = React.useState<PickedEntry[]>([]);

    React.useEffect(() => {
        if (!open) return;
        const t = setTimeout(async () => {
            const params = new URLSearchParams();
            collections.forEach((c) => params.append('collections[]', c));
            if (query) params.set('q', query);
            const res = await fetch(`${adminUrl('api/entries', adminPath)}?${params}`, { headers: { Accept: 'application/json' } });
            const json = await res.json();
            setResults(json.data ?? []);
        }, 200);
        return () => clearTimeout(t);
    }, [open, query, collections, adminPath]);

    const toggle = (entry: PickedEntry) => {
        if (multiple) {
            onChange(value.some((v) => v.id === entry.id) ? value.filter((v) => v.id !== entry.id) : [...value, entry]);
        } else {
            onChange([entry]);
            setOpen(false);
        }
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button variant="outline" role="combobox" className="w-full justify-between font-normal">
                    {value.length > 0 ? value.map((v) => v.title).join(', ') : placeholder}
                    <ChevronsUpDown className="ml-2 h-4 w-4 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-80 p-0">
                <Command shouldFilter={false}>
                    <CommandInput placeholder="Search entries…" value={query} onValueChange={setQuery} />
                    <CommandList>
                        <CommandEmpty>No entries found.</CommandEmpty>
                        <CommandGroup>
                            {results.map((entry) => (
                                <CommandItem key={entry.id} value={String(entry.id)} onSelect={() => toggle(entry)}>
                                    <Check className={`h-4 w-4 ${value.some((v) => v.id === entry.id) ? 'opacity-100' : 'opacity-0'}`} />
                                    <span className="flex-1 truncate">{entry.title}</span>
                                    <span className="text-xs text-muted-foreground">{entry.collection}</span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
