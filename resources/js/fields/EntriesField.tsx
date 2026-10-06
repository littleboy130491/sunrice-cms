import { X } from 'lucide-react';
import EntryPicker, { PickedEntry, useEntryTitles } from '@/components/EntryPicker';
import type { FieldProps } from './types';

export default function EntriesField({ field, value, onChange }: FieldProps) {
    const collections = (field.config?.collections as string[] | undefined) ?? [];
    const max = (field.config?.max as number | undefined) ?? undefined;
    const ids = Array.isArray(value) ? (value as number[]) : [];
    const titles = useEntryTitles(ids);
    const full = !!max && ids.length >= max;

    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap gap-2">
                {ids.map((id) => (
                    <span key={id} className="flex items-center gap-1 rounded-md border px-2 py-1 text-sm">
                        {titles[id] ?? `Entry #${id}`}
                        <button type="button" aria-label={`Remove ${titles[id] ?? 'entry'}`} onClick={() => onChange(ids.filter((v) => v !== id))}>
                            <X className="h-3 w-3" />
                        </button>
                    </span>
                ))}
            </div>
            {full ? (
                <p className="text-xs text-muted-foreground">Maximum of {max} reached.</p>
            ) : (
            <EntryPicker
                collections={collections}
                multiple
                value={[]}
                onChange={(entries: PickedEntry[]) => {
                    const next = [...ids];
                    entries.forEach((e) => {
                        if (!next.includes(e.id) && (!max || next.length < max)) next.push(e.id);
                    });
                    onChange(next);
                }}
            />
            )}
        </div>
    );
}
