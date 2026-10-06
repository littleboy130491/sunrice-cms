import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { adminUrl } from '@/lib/route';
import type { FieldProps } from './types';
import type { SharedProps } from '@/types';

interface Option { id: number | string; label: string }

/**
 * belongs_to / belongs_to_many select backed by the resource options
 * API: GET {admin}/api/resources/{resource}/options/{field}?q=
 */
export default function BelongsToField({ field, value, onChange }: FieldProps) {
    const { adminPath } = usePage<SharedProps>().props;
    const resource = (field.config?.resource as string) ?? '';
    const multiple = field.type === 'belongs_to_many' || !!field.config?.multiple;
    const [query, setQuery] = React.useState('');
    const [options, setOptions] = React.useState<Option[]>([]);

    React.useEffect(() => {
        if (!resource) return;
        const handle = setTimeout(() => {
            fetch(adminUrl(`api/resources/${resource}/options/${field.handle}?q=${encodeURIComponent(query)}`, adminPath))
                .then((r) => r.json())
                .then((d) => setOptions(d.options ?? []))
                .catch(() => setOptions([]));
        }, 200);
        return () => clearTimeout(handle);
    }, [query, resource, field.handle, adminPath]);

    if (multiple) {
        const selected = (value as (number | string)[]) ?? [];
        return (
            <div className="space-y-2">
                <Label>{field.label ?? field.handle}</Label>
                <Input placeholder="Search…" value={query} onChange={(e) => setQuery(e.target.value)} />
                <div className="flex flex-col gap-1.5">
                    {options.map((o) => (
                        <label key={o.id} className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={selected.includes(o.id)}
                                onCheckedChange={(checked) =>
                                    onChange(checked ? [...selected, o.id] : selected.filter((v) => v !== o.id))
                                }
                            />
                            {o.label}
                        </label>
                    ))}
                </div>
            </div>
        );
    }

    const current = value as number | string | null;
    return (
        <div className="space-y-2">
            <Label>{field.label ?? field.handle}</Label>
            <Input placeholder="Search…" value={query} onChange={(e) => setQuery(e.target.value)} />
            <div className="flex flex-col gap-1.5">
                {options.map((o) => (
                    <label key={o.id} className="flex items-center gap-2 text-sm">
                        <input
                            type="radio"
                            checked={current === o.id}
                            onChange={() => onChange(o.id)}
                            className="accent-primary"
                        />
                        {o.label}
                    </label>
                ))}
                {current != null && !options.some((o) => o.id === current) && (
                    <span className="text-sm text-muted-foreground">Selected: {String(current)}</span>
                )}
            </div>
        </div>
    );
}
