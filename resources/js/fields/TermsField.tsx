import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { ChevronsUpDown, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { adminUrl } from '@/lib/route';
import type { FieldProps } from './types';
import type { SharedProps } from '@/types';
import { fetchJson } from '@/lib/fetch-json';
import { toast } from 'sonner';

interface TermOption {
    id: number;
    title: string;
}

export default function TermsField({ field, value, onChange }: FieldProps) {
    const { adminPath } = usePage<SharedProps>().props;
    const taxonomy = field.config?.taxonomy as string | undefined;
    const single = field.config?.max === 1;
    const [open, setOpen] = React.useState(false);
    const [query, setQuery] = React.useState('');
    const [options, setOptions] = React.useState<TermOption[]>([]);
    const ids = Array.isArray(value) ? (value as number[]) : [];
    // Titles of picked terms, remembered as they're seen or looked up.
    const [titles, setTitles] = React.useState<Record<number, string>>({});
    const remember = (list: TermOption[]) => setTitles((t) => ({ ...t, ...Object.fromEntries(list.map((o) => [o.id, o.title])) }));
    const missing = ids.filter((id) => !(id in titles)).join(',');
    React.useEffect(() => {
        if (!missing) return;
        const params = new URLSearchParams();
        missing.split(',').forEach((id) => params.append('ids[]', id));
        fetchJson<{ data?: TermOption[] }>(`${adminUrl('api/terms', adminPath)}?${params}`)
            .then((json) => remember(json.data ?? []))
            .catch(() => undefined);
    }, [missing, adminPath]);

    React.useEffect(() => {
        if (!open || !taxonomy) return;
        const t = setTimeout(async () => {
            const params = new URLSearchParams({ taxonomy });
            if (query) params.set('q', query);
            try {
                const json = await fetchJson<{ data?: TermOption[] }>(`${adminUrl('api/terms', adminPath)}?${params}`);
                setOptions(json.data ?? []);
                remember(json.data ?? []);
            } catch (e) {
                toast.error(e instanceof Error ? e.message : 'Could not load terms.');
            }
        }, 200);
        return () => clearTimeout(t);
    }, [open, query, taxonomy, adminPath]);

    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap gap-2">
                {ids.map((id) => (
                    <span key={id} className="flex items-center gap-1 rounded-md border px-2 py-1 text-sm">
                        {titles[id] ?? `Term #${id}`}
                        <button type="button" aria-label={`Remove ${titles[id] ?? 'term'}`} onClick={() => onChange(ids.filter((v) => v !== id))}>
                            <X className="h-3 w-3" />
                        </button>
                    </span>
                ))}
            </div>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button variant="outline" className="w-full justify-between font-normal">
                        {single && ids.length > 0 ? 'Change term…' : 'Add term…'} <ChevronsUpDown className="ml-2 h-4 w-4 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-80 p-0">
                    <Command shouldFilter={false}>
                        <CommandInput placeholder="Search terms…" value={query} onValueChange={setQuery} />
                        <CommandList>
                            <CommandEmpty>{options.length > 0 ? 'All matching terms are already chosen.' : 'No terms.'}</CommandEmpty>
                            <CommandGroup>
                                {/* Already chosen terms show above as chips; they aren't offered again. */}
                                {options.filter((o) => !ids.includes(o.id)).map((o) => (
                                    <CommandItem
                                        key={o.id}
                                        value={String(o.id)}
                                        onSelect={() => {
                                            if (single) {
                                                // One term only: picking replaces it.
                                                onChange([o.id]);
                                                setOpen(false);
                                                return;
                                            }
                                            onChange([...ids, o.id]);
                                        }}
                                    >
                                        {o.title}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>
    );
}
