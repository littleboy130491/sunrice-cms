import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import EntryPicker, { PickedEntry } from '@/components/EntryPicker';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

interface Item {
    id: number;
    parent_id: number | null;
    type: string;
    target_id: number | null;
    url: string | null;
    labels: Record<string, string>;
    new_tab: boolean;
}

interface Props {
    menu: { id: number; handle: string; title: string };
    items: Item[];
}

export default function MenuEdit({ menu, items }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const [open, setOpen] = React.useState(false);
    const [parentId, setParentId] = React.useState<number | null>(null);
    const [form, setForm] = React.useState<{ type: string; target_id: number | null; url: string; labels: Record<string, string>; new_tab: boolean; picked: PickedEntry[] }>({
        type: 'url', target_id: null, url: '', labels: {}, new_tab: false, picked: [],
    });

    const roots = items.filter((i) => i.parent_id === null);
    const childrenOf = (id: number) => items.filter((i) => i.parent_id === id);

    const openCreate = (parent: number | null) => {
        setParentId(parent);
        setForm({ type: 'url', target_id: null, url: '', labels: {}, new_tab: false, picked: [] });
        setOpen(true);
    };

    const submit = () => {
        router.post(adminUrl(`menus/${menu.id}/items`, adminPath), {
            parent_id: parentId,
            type: form.type,
            target_id: form.type === 'entry' || form.type === 'term' ? form.target_id : null,
            url: form.type === 'url' ? form.url : null,
            labels: form.labels,
            new_tab: form.new_tab,
        }, { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    const remove = (id: number) =>
        window.confirm('Remove item?') && router.delete(adminUrl(`menu-items/${id}`, adminPath), { preserveScroll: true });

    const label = (i: Item) => Object.values(i.labels ?? {})[0] ?? `#${i.id}`;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-semibold">{menu.title} <code className="text-sm text-muted-foreground">{menu.handle}</code></h1>
                <Button onClick={() => openCreate(null)}><Plus className="mr-1 h-4 w-4" /> Add item</Button>
            </div>

            <ul className="flex flex-col gap-1">
                {roots.map((item) => (
                    <li key={item.id}>
                        <div className="flex items-center justify-between rounded-md border px-3 py-2">
                            <span className="text-sm">{label(item)} <span className="text-xs text-muted-foreground">({item.type})</span></span>
                            <span className="flex items-center gap-1">
                                <Button variant="ghost" size="sm" onClick={() => openCreate(item.id)}><Plus className="h-3 w-3" /></Button>
                                <Button variant="ghost" size="sm" className="text-destructive" onClick={() => remove(item.id)}><Trash2 className="h-4 w-4" /></Button>
                            </span>
                        </div>
                        <ul className="ml-6 mt-1 flex flex-col gap-1">
                            {childrenOf(item.id).map((child) => (
                                <li key={child.id} className="flex items-center justify-between rounded-md border px-3 py-1.5">
                                    <span className="text-sm">{label(child)} <span className="text-xs text-muted-foreground">({child.type})</span></span>
                                    <Button variant="ghost" size="sm" className="text-destructive" onClick={() => remove(child.id)}><Trash2 className="h-4 w-4" /></Button>
                                </li>
                            ))}
                        </ul>
                    </li>
                ))}
                {roots.length === 0 && <li className="py-8 text-center text-sm text-muted-foreground">No items yet.</li>}
            </ul>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader><DialogTitle>Add menu item</DialogTitle></DialogHeader>
                    <div className="flex flex-col gap-3">
                        <div className="grid gap-2">
                            <Label>Type</Label>
                            <Select value={form.type} onValueChange={(v) => setForm({ ...form, type: v })}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="url">URL</SelectItem>
                                    <SelectItem value="entry">Entry</SelectItem>
                                    <SelectItem value="collection">Collection</SelectItem>
                                    <SelectItem value="term">Term</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        {form.type === 'url' ? (
                            <div className="grid gap-2">
                                <Label>URL</Label>
                                <Input value={form.url} onChange={(e) => setForm({ ...form, url: e.target.value })} placeholder="/about or https://…" />
                            </div>
                        ) : form.type === 'entry' ? (
                            <EntryPicker
                                value={form.picked}
                                onChange={(picked) => setForm({ ...form, picked, target_id: picked[0]?.id ?? null })}
                            />
                        ) : (
                            <div className="grid gap-2">
                                <Label>Target id</Label>
                                <Input type="number" value={form.target_id ?? ''} onChange={(e) => setForm({ ...form, target_id: Number(e.target.value) || null })} />
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label>Label</Label>
                            <Input value={form.labels.id ?? ''} onChange={(e) => setForm({ ...form, labels: { id: e.target.value } })} />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={form.new_tab} onCheckedChange={(c) => setForm({ ...form, new_tab: !!c })} />
                            Open in new tab
                        </label>
                        <Button onClick={submit}>Add</Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
