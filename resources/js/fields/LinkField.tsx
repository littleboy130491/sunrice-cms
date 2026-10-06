import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import EntryPicker, { useEntryTitles } from '@/components/EntryPicker';
import type { FieldProps } from './types';

/** Stored shape, matching the server's Link field type. */
interface LinkValue {
    type: 'url' | 'entry';
    url?: string | null;
    entry_id?: number | null;
    label?: string | null;
    new_tab?: boolean;
}

export default function LinkField({ field, value, onChange }: FieldProps) {
    const raw = (value as (LinkValue & { entry?: number | null }) | null) ?? { type: 'url' };
    // Older values kept the entry under `entry`.
    const v: LinkValue = { ...raw, entry_id: raw.entry_id ?? raw.entry ?? null };
    const titles = useEntryTitles(v.entry_id ? [v.entry_id] : []);
    const collections = (field.config?.collections as string[] | undefined) ?? [];
    const set = (patch: Partial<LinkValue>) => onChange({ type: v.type, url: v.url ?? null, entry_id: v.entry_id ?? null, label: v.label ?? null, new_tab: !!v.new_tab, ...patch });

    return (
        <div className="flex flex-col gap-2">
            <div className="flex gap-2">
                <Select value={v.type} onValueChange={(type) => set({ type: type as LinkValue['type'] })}>
                    <SelectTrigger className="w-36">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="url">URL</SelectItem>
                        <SelectItem value="entry">Entry</SelectItem>
                    </SelectContent>
                </Select>
                <div className="flex-1">
                    {v.type === 'url' ? (
                        <Input placeholder="https://…" value={v.url ?? ''} onChange={(e) => set({ url: e.target.value })} />
                    ) : (
                        <EntryPicker
                            collections={collections}
                            value={v.entry_id ? [{ id: v.entry_id, title: titles[v.entry_id] ?? `Entry #${v.entry_id}`, collection: '' }] : []}
                            onChange={(entries) => set({ entry_id: entries[0]?.id ?? null })}
                        />
                    )}
                </div>
            </div>
            <div className="flex items-center gap-3">
                <Input className="flex-1" placeholder={v.type === 'entry' ? 'Link text (defaults to the entry title)' : 'Link text'} value={v.label ?? ''} onChange={(e) => set({ label: e.target.value })} />
                <label className="flex shrink-0 items-center gap-2 text-sm">
                    <Checkbox checked={!!v.new_tab} onCheckedChange={(c) => set({ new_tab: !!c })} />
                    New tab
                </label>
            </div>
        </div>
    );
}
