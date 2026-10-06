import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import EntryPicker from '@/components/EntryPicker';
import type { FieldProps } from './types';

interface LinkValue {
    type: 'url' | 'entry';
    url?: string;
    entry?: number | null;
}

export default function LinkField({ value, onChange }: FieldProps) {
    const v = (value as LinkValue) ?? { type: 'url' };

    return (
        <div className="flex flex-col gap-2">
            <Select value={v.type} onValueChange={(type) => onChange({ ...v, type })}>
                <SelectTrigger className="w-36">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="url">URL</SelectItem>
                    <SelectItem value="entry">Entry</SelectItem>
                </SelectContent>
            </Select>
            {v.type === 'url' ? (
                <Input placeholder="https://…" value={v.url ?? ''} onChange={(e) => onChange({ ...v, url: e.target.value })} />
            ) : (
                <EntryPicker
                    value={v.entry ? [{ id: v.entry, title: `#${v.entry}`, collection: '' }] : []}
                    onChange={(entries) => onChange({ ...v, entry: entries[0]?.id ?? null })}
                />
            )}
        </div>
    );
}
