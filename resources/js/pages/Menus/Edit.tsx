import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ExternalLink, Pencil, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import EntryPicker, { PickedEntry } from '@/components/EntryPicker';
import TermPicker, { PickedTerm } from '@/components/TermPicker';
import { InputError } from '@/components/app/input-error';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';

type ItemType = 'url' | 'entry' | 'collection' | 'term';

interface Item {
    id: number;
    parent_id: number | null;
    type: ItemType;
    target_id: number | null;
    target_title: string | null;
    target_taxonomy?: string | null;
    url: string | null;
    labels: Record<string, string>;
    new_tab: boolean;
}

interface Props {
    menu: { id: number; handle: string; title: string };
    items: Item[];
    collections: { id: number; title: string; has_archive: boolean }[];
    taxonomies: { id: number; handle: string; title: string }[];
}

interface FormState {
    type: ItemType;
    url: string;
    entry: PickedEntry[];
    collectionId: string;
    taxonomy: string;
    term: PickedTerm | null;
    labels: Record<string, string>;
    new_tab: boolean;
}

const TYPE_LABELS: Record<ItemType, string> = { url: 'URL', entry: 'Entry', collection: 'Collection archive', term: 'Term page' };

const emptyForm = (taxonomy: string): FormState => ({
    type: 'entry', url: '', entry: [], collectionId: '', taxonomy, term: null, labels: {}, new_tab: false,
});

export default function MenuEdit({ menu, items, collections, taxonomies }: Props) {
    const { adminPath, locales } = usePage<SharedProps>().props;
    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [processing, setProcessing] = React.useState(false);
    const can = useCan();
    const canEdit = can('sunrice.menus.edit');
    const [open, setOpen] = React.useState(false);
    const [editing, setEditing] = React.useState<Item | null>(null);
    const [parentId, setParentId] = React.useState<number | null>(null);
    const [form, setForm] = React.useState<FormState>(emptyForm(taxonomies[0]?.handle ?? ''));

    const roots = items.filter((i) => i.parent_id === null);
    const childrenOf = (id: number) => items.filter((i) => i.parent_id === id);

    const openCreate = (parent: number | null) => {
        setEditing(null);
        setParentId(parent);
        setForm(emptyForm(taxonomies[0]?.handle ?? ''));
        setErrors({});
        setOpen(true);
    };

    const openEdit = (item: Item) => {
        setEditing(item);
        setParentId(item.parent_id);
        setForm({
            ...emptyForm(item.target_taxonomy ?? taxonomies[0]?.handle ?? ''),
            type: item.type,
            url: item.url ?? '',
            entry: item.type === 'entry' && item.target_id ? [{ id: item.target_id, title: item.target_title ?? `#${item.target_id}`, collection: '' }] : [],
            collectionId: item.type === 'collection' && item.target_id ? String(item.target_id) : '',
            term: item.type === 'term' && item.target_id ? { id: item.target_id, title: item.target_title ?? `#${item.target_id}` } : null,
            labels: item.labels ?? {},
            new_tab: item.new_tab,
        });
        setErrors({});
        setOpen(true);
    };

    const targetId = (): number | null => {
        switch (form.type) {
            case 'entry': return form.entry[0]?.id ?? null;
            case 'collection': return form.collectionId ? Number(form.collectionId) : null;
            case 'term': return form.term?.id ?? null;
            default: return null;
        }
    };

    const submit = () => {
        const payload = {
            parent_id: parentId,
            type: form.type,
            target_id: targetId(),
            url: form.type === 'url' ? form.url : null,
            labels: form.labels,
            new_tab: form.new_tab,
        };
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => { setOpen(false); setErrors({}); },
            onError: (e: Record<string, string>) => setErrors(e),
        };
        if (editing) {
            router.put(adminUrl(`menu-items/${editing.id}`, adminPath), payload, options);
        } else {
            router.post(adminUrl(`menus/${menu.id}/items`, adminPath), payload, options);
        }
    };

    const remove = (id: number) =>
        window.confirm('Remove item?') && router.delete(adminUrl(`menu-items/${id}`, adminPath), { preserveScroll: true });

    const label = (i: Item) => i.labels?.[locales.main] || Object.values(i.labels ?? {})[0] || i.target_title || i.url || `#${i.id}`;
    const selectedCollection = collections.find((c) => String(c.id) === form.collectionId);

    // Move an item up or down among its siblings; the whole tree order is sent.
    const move = (item: Item, dir: -1 | 1) => {
        const siblings = item.parent_id === null ? roots : childrenOf(item.parent_id);
        const i = siblings.findIndex((s) => s.id === item.id);
        const j = i + dir;
        if (j < 0 || j >= siblings.length) return;
        const reordered = [...siblings];
        [reordered[i], reordered[j]] = [reordered[j], reordered[i]];
        const order: { id: number; parent_id: number | null }[] = [];
        for (const root of item.parent_id === null ? reordered : roots) {
            order.push({ id: root.id, parent_id: null });
            for (const child of item.parent_id === root.id ? reordered : childrenOf(root.id)) {
                order.push({ id: child.id, parent_id: root.id });
            }
        }
        router.post(adminUrl(`menus/${menu.id}/items/reorder`, adminPath), { items: order }, { preserveScroll: true });
    };

    const row = (item: Item, nested: boolean) => {
        const siblings = item.parent_id === null ? roots : childrenOf(item.parent_id);
        const position = siblings.findIndex((s) => s.id === item.id);

        return (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2">
            <span className="flex min-w-0 flex-1 items-center gap-2 text-sm">
                <span className="truncate font-medium">{label(item)}</span>
                <Badge variant="secondary">{TYPE_LABELS[item.type]}</Badge>
                {item.type === 'url' ? (
                    <code className="truncate text-xs text-muted-foreground">{item.url}</code>
                ) : (
                    <span className="truncate text-xs text-muted-foreground">{item.target_title ?? 'Missing target'}</span>
                )}
                {item.new_tab && <ExternalLink className="size-3 shrink-0 text-muted-foreground" />}
            </span>
            {canEdit && (
                <span className="flex shrink-0 items-center gap-1">
                    <Button variant="ghost" size="sm" onClick={() => move(item, -1)} disabled={position <= 0} aria-label="Move up"><ArrowUp className="h-3.5 w-3.5" /></Button>
                    <Button variant="ghost" size="sm" onClick={() => move(item, 1)} disabled={position >= siblings.length - 1} aria-label="Move down"><ArrowDown className="h-3.5 w-3.5" /></Button>
                    <Button variant="ghost" size="sm" onClick={() => openEdit(item)} aria-label="Edit"><Pencil className="h-3.5 w-3.5" /></Button>
                    {!nested && <Button variant="ghost" size="sm" onClick={() => openCreate(item.id)} aria-label="Add child"><Plus className="h-3.5 w-3.5" /></Button>}
                    <Button variant="ghost" size="sm" className="text-destructive" onClick={() => remove(item.id)} aria-label="Remove"><Trash2 className="h-4 w-4" /></Button>
                </span>
            )}
        </div>
        );
    };

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold tracking-tight">{menu.title} <code className="text-sm text-muted-foreground">{menu.handle}</code></h1>
                {canEdit && <Button onClick={() => openCreate(null)}><Plus className="mr-1 h-4 w-4" /> Add item</Button>}
            </div>

            <ul className="flex flex-col gap-1">
                {roots.map((item) => (
                    <li key={item.id}>
                        {row(item, false)}
                        <ul className="ml-6 mt-1 flex flex-col gap-1">
                            {childrenOf(item.id).map((child) => <li key={child.id}>{row(child, true)}</li>)}
                        </ul>
                    </li>
                ))}
                {roots.length === 0 && <li className="py-8 text-center text-sm text-muted-foreground">No items yet.</li>}
            </ul>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader><DialogTitle>{editing ? 'Edit menu item' : 'Add menu item'}</DialogTitle></DialogHeader>
                    <div className="flex flex-col gap-4">
                        <div className="grid gap-2">
                            <Label>Links to</Label>
                            <Select value={form.type} onValueChange={(v) => setForm({ ...form, type: v as ItemType })}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {(Object.keys(TYPE_LABELS) as ItemType[]).map((t) => <SelectItem key={t} value={t}>{TYPE_LABELS[t]}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>

                        {form.type === 'url' && (
                            <div className="grid gap-2">
                                <Label>URL</Label>
                                <Input value={form.url} onChange={(e) => setForm({ ...form, url: e.target.value })} placeholder="/about or https://…" />
                                <InputError message={errors.url} />
                            </div>
                        )}
                        {form.type === 'entry' && (
                            <div className="grid gap-2">
                                <Label>Entry</Label>
                                <EntryPicker
                                    linkable value={form.entry} onChange={(entry) => setForm({ ...form, entry })} />
                                <InputError message={errors.target_id} />
                            </div>
                        )}
                        {form.type === 'collection' && (
                            <div className="grid gap-2">
                                <Label>Collection</Label>
                                <Select value={form.collectionId} onValueChange={(v) => setForm({ ...form, collectionId: v })}>
                                    <SelectTrigger><SelectValue placeholder="Choose a collection…" /></SelectTrigger>
                                    <SelectContent>
                                        {collections.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.title}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                                {selectedCollection && !selectedCollection.has_archive && (
                                    <p className="text-xs text-amber-600">This collection has no archive page, so the item stays hidden until you enable one.</p>
                                )}
                                <InputError message={errors.target_id} />
                            </div>
                        )}
                        {form.type === 'term' && (
                            <div className="grid gap-2">
                                <Label>Term</Label>
                                <div className="grid grid-cols-[10rem_1fr] gap-2">
                                    <Select value={form.taxonomy} onValueChange={(v) => setForm({ ...form, taxonomy: v, term: null })}>
                                        <SelectTrigger><SelectValue placeholder="Taxonomy" /></SelectTrigger>
                                        <SelectContent>
                                            {taxonomies.map((t) => <SelectItem key={t.id} value={t.handle}>{t.title}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                    <TermPicker taxonomy={form.taxonomy} value={form.term} onChange={(term) => setForm({ ...form, term })} />
                                </div>
                                {taxonomies.length === 0 && <p className="text-xs text-muted-foreground">No taxonomies yet.</p>}
                                <InputError message={errors.target_id} />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label>Label</Label>
                            {locales.available.map((lc) => (
                                <div key={lc} className="flex items-center gap-2">
                                    <span className="w-8 text-xs font-medium uppercase text-muted-foreground">{lc}</span>
                                    <Input
                                        value={form.labels[lc] ?? ''}
                                        placeholder={form.type === 'url' ? '' : 'Uses the linked title'}
                                        onChange={(e) => setForm({ ...form, labels: { ...form.labels, [lc]: e.target.value } })}
                                    />
                                    <InputError message={errors[`labels.${lc}`]} />
                                </div>
                            ))}
                            <InputError message={errors.labels} />
                            <p className="text-xs text-muted-foreground">Empty languages fall back to the {locales.main.toUpperCase()} label, then the linked title.</p>
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={form.new_tab} onCheckedChange={(c) => setForm({ ...form, new_tab: !!c })} />
                            Open in new tab
                        </label>
                        <InputError message={errors.parent_id ?? errors.type ?? errors.new_tab} />
                        <Button onClick={submit} disabled={processing}>{editing ? 'Save' : 'Add'}</Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
