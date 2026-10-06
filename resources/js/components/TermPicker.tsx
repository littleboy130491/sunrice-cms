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

export interface PickedTerm {
    id: number;
    title: string;
}

interface Props {
    taxonomy: string;
    value: PickedTerm | null;
    onChange: (term: PickedTerm) => void;
}

/**
 * Searchable single-term picker for one taxonomy, backed by
 * `GET {admin}/api/terms`.
 */
export default function TermPicker({ taxonomy, value, onChange }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const [open, setOpen] = React.useState(false);
    const [query, setQuery] = React.useState('');
    const [results, setResults] = React.useState<PickedTerm[]>([]);

    React.useEffect(() => {
        if (!open || !taxonomy) return;
        const t = setTimeout(async () => {
            const params = new URLSearchParams({ taxonomy });
            if (query) params.set('q', query);
            try {
                const json = await fetchJson<{ data?: PickedTerm[] }>(`${adminUrl('api/terms', adminPath)}?${params}`);
                setResults(json.data ?? []);
            } catch (e) {
                toast.error(e instanceof Error ? e.message : 'Could not load terms.');
            }
        }, 200);
        return () => clearTimeout(t);
    }, [open, query, taxonomy, adminPath]);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button variant="outline" role="combobox" className="w-full justify-between font-normal" disabled={!taxonomy}>
                    {value?.title ?? 'Pick term…'}
                    <ChevronsUpDown className="ml-2 h-4 w-4 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-80 p-0">
                <Command shouldFilter={false}>
                    <CommandInput placeholder="Search terms…" value={query} onValueChange={setQuery} />
                    <CommandList>
                        <CommandEmpty>No terms found.</CommandEmpty>
                        <CommandGroup>
                            {results.map((term) => (
                                <CommandItem
                                    key={term.id}
                                    value={String(term.id)}
                                    onSelect={() => {
                                        onChange(term);
                                        setOpen(false);
                                    }}
                                >
                                    <Check className={`h-4 w-4 ${value?.id === term.id ? 'opacity-100' : 'opacity-0'}`} />
                                    <span className="flex-1 truncate">{term.title}</span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
