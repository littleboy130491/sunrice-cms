import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';
import { fetchJson } from '@/lib/fetch-json';
import { toast } from 'sonner';

export interface PickedEntry {
    id: number;
    title: string;
    collection: string;
}

interface Props {
    collections?: string[];
    multiple?: boolean;
    value: PickedEntry[];
    /** Entry ids to leave out of the options (already chosen elsewhere). */
    exclude?: number[];
    onChange: (entries: PickedEntry[]) => void;
    placeholder?: string;
    /** Only entries that have a page of their own (for links and menus). */
    linkable?: boolean;
}

/**
 * Searchable entry picker backed by `GET {admin}/api/entries`.
 */
/**
 * Titles for already-picked entry ids, for showing a value that only
 * stores ids. Unknown ids fall back to "Entry #id".
 */
export function useEntryTitles(ids: number[]): Record<number, string> {
    const { adminPath } = usePage<SharedProps>().props;
    const [titles, setTitles] = React.useState<Record<number, string>>({});
    const key = ids.filter((id) => !(id in titles)).join(',');

    React.useEffect(() => {
        if (!key) return;
        const params = new URLSearchParams();
        key.split(',').forEach((id) => params.append('ids[]', id));
        fetchJson<{ data?: PickedEntry[] }>(`${adminUrl('api/entries', adminPath)}?${params}`)
            .then((json) => setTitles((t) => ({ ...t, ...Object.fromEntries((json.data ?? []).map((e) => [e.id, e.title])) })))
            .catch(() => undefined);
    }, [key, adminPath]);

    return titles;
}

export default function EntryPicker({ collections = [], multiple = false, value, onChange, placeholder = 'Pick entry…', linkable = false, exclude = [] }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const [open, setOpen] = React.useState(false);
    const [query, setQuery] = React.useState('');
    const [results, setResults] = React.useState<PickedEntry[]>([]);

    React.useEffect(() => {
        if (!open) return;
        const t = setTimeout(async () => {
            const params = new URLSearchParams();
            collections.forEach((c) => params.append('collections[]', c));
            if (linkable) params.set('linkable', '1');
            if (query) params.set('q', query);
            try {
                const json = await fetchJson<{ data?: PickedEntry[] }>(`${adminUrl('api/entries', adminPath)}?${params}`);
                setResults(json.data ?? []);
            } catch (e) {
                toast.error(e instanceof Error ? e.message : 'Could not load entries.');
            }
        }, 200);
        return () => clearTimeout(t);
        // Compare collections by value: callers often pass a new array each render.
    }, [open, query, collections.join(','), adminPath, linkable]);

    // Already chosen: not offered again (remove it from the chosen list instead).
    const hidden = new Set([...exclude, ...(multiple ? value.map((v) => v.id) : [])]);
    const options = results.filter((entry) => !hidden.has(entry.id));

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
                        <CommandEmpty>{results.length > 0 ? 'All matching entries are already chosen.' : 'No entries found.'}</CommandEmpty>
                        <CommandGroup>
                            {options.map((entry) => (
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
