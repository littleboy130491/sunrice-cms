import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { adminUrl } from '@/lib/route';
import type { FieldProps } from './types';
import type { SharedProps } from '@/types';

interface TermOption {
    id: number;
    title: string;
}

export default function TermsField({ field, value, onChange }: FieldProps) {
    const { adminPath } = usePage<SharedProps>().props;
    const taxonomy = field.config?.taxonomy as string | undefined;
    const [open, setOpen] = React.useState(false);
    const [query, setQuery] = React.useState('');
    const [options, setOptions] = React.useState<TermOption[]>([]);
    const ids = (value as number[]) ?? [];

    React.useEffect(() => {
        if (!open || !taxonomy) return;
        const t = setTimeout(async () => {
            const params = new URLSearchParams({ taxonomy });
            if (query) params.set('q', query);
            const res = await fetch(`${adminUrl('api/terms', adminPath)}?${params}`, { headers: { Accept: 'application/json' } });
            const json = await res.json();
            setOptions(json.data ?? []);
        }, 200);
        return () => clearTimeout(t);
    }, [open, query, taxonomy, adminPath]);

    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap gap-2">
                {ids.map((id) => (
                    <span key={id} className="flex items-center gap-1 rounded-md border px-2 py-1 text-sm">
                        {options.find((o) => o.id === id)?.title ?? `Term #${id}`}
                        <button type="button" onClick={() => onChange(ids.filter((v) => v !== id))}>
                            <X className="h-3 w-3" />
                        </button>
                    </span>
                ))}
            </div>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button variant="outline" className="w-full justify-between font-normal">
                        Add term… <ChevronsUpDown className="ml-2 h-4 w-4 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-80 p-0">
                    <Command shouldFilter={false}>
                        <CommandInput placeholder="Search terms…" value={query} onValueChange={setQuery} />
                        <CommandList>
                            <CommandEmpty>No terms.</CommandEmpty>
                            <CommandGroup>
                                {options.map((o) => (
                                    <CommandItem
                                        key={o.id}
                                        value={String(o.id)}
                                        onSelect={() => onChange(ids.includes(o.id) ? ids.filter((v) => v !== o.id) : [...ids, o.id])}
                                    >
                                        <Check className={`h-4 w-4 ${ids.includes(o.id) ? 'opacity-100' : 'opacity-0'}`} />
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
