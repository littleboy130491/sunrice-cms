import * as React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import EntryPicker, { PickedEntry } from '@/components/EntryPicker';
import TermPicker, { PickedTerm } from '@/components/TermPicker';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { InputError } from '@/components/app/input-error';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { adminUrl } from '@/lib/route';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';
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
    item: Item | null;
    parent: Item | null;
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

const initialForm = (item: Item | null, defaultTaxonomy: string): FormState => ({
    type: item?.type ?? 'entry',
    url: item?.url ?? '',
    entry: item?.type === 'entry' && item.target_id ? [{ id: item.target_id, title: item.target_title ?? `#${item.target_id}`, collection: '' }] : [],
    collectionId: item?.type === 'collection' && item.target_id ? String(item.target_id) : '',
    taxonomy: item?.target_taxonomy ?? defaultTaxonomy,
    term: item?.type === 'term' && item.target_id ? { id: item.target_id, title: item.target_title ?? `#${item.target_id}` } : null,
    labels: item?.labels ?? {},
    new_tab: item?.new_tab ?? false,
});

const itemLabel = (i: Item, main: string) => i.labels?.[main] || Object.values(i.labels ?? {})[0] || i.target_title || i.url || `#${i.id}`;

/** Add or edit one menu item: what it links to, its label per language, new tab. */
export default function MenuItemEdit({ menu, item, parent, collections, taxonomies }: Props) {
    const { adminPath, locales } = usePage<SharedProps>().props;
    const isNew = item === null;
    const [form, setForm] = React.useState<FormState>(() => initialForm(item, taxonomies[0]?.handle ?? ''));
    const saved = React.useMemo(() => JSON.stringify(initialForm(item, taxonomies[0]?.handle ?? '')), [item, taxonomies]);
    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [processing, setProcessing] = React.useState(false);
    const formRef = React.useRef<HTMLFormElement>(null);
    const dirty = JSON.stringify(form) !== saved;
    useUnsavedChanges(dirty && !processing, () => formRef.current?.requestSubmit());

    const menuUrl = adminUrl(`menus/${menu.id}`, adminPath);
    useBreadcrumbs([
        { label: 'Structure' },
        { label: 'Menus', href: adminUrl('menus', adminPath) },
        { label: menu.title, href: menuUrl },
        { label: isNew ? 'Add item' : itemLabel(item, locales.main) },
    ]);

    const targetId = (): number | null => {
        switch (form.type) {
            case 'entry': return form.entry[0]?.id ?? null;
            case 'collection': return form.collectionId ? Number(form.collectionId) : null;
            case 'term': return form.term?.id ?? null;
            default: return null;
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const payload = {
            parent_id: item?.parent_id ?? parent?.id ?? null,
            type: form.type,
            target_id: targetId(),
            url: form.type === 'url' ? form.url : null,
            labels: form.labels,
            new_tab: form.new_tab,
        };
        const options = {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (e: Record<string, string>) => setErrors(e),
        };
        if (isNew) {
            router.post(adminUrl(`menus/${menu.id}/items`, adminPath), payload, options);
        } else {
            router.put(adminUrl(`menu-items/${item.id}`, adminPath), payload, options);
        }
    };

    const remove = () => {
        if (item && window.confirm('Remove this item from the menu? Its sub-items are removed too.')) {
            router.delete(adminUrl(`menu-items/${item.id}`, adminPath));
        }
    };

    const selectedCollection = collections.find((c) => String(c.id) === form.collectionId);

    return (
        <form ref={formRef} onSubmit={submit} className="flex max-w-2xl flex-col gap-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex min-w-0 items-start gap-3">
                    <Button variant="outline" size="icon" className="size-8 shrink-0" asChild>
                        <Link href={menuUrl} aria-label={`Back to ${menu.title}`}><ArrowLeft /></Link>
                    </Button>
                    <div className="min-w-0 space-y-1">
                        <h1 className="truncate sunrice-page-title">{isNew ? 'Add menu item' : itemLabel(item, locales.main)}</h1>
                        <p className="text-sm text-muted-foreground">
                            {menu.title}
                            {parent && <> · under “{itemLabel(parent, locales.main)}”</>}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {!isNew && (
                        <Button type="button" variant="outline" size="icon" className="text-destructive" aria-label="Remove item" onClick={remove}>
                            <Trash2 />
                        </Button>
                    )}
                    <Button type="submit" disabled={processing}>{processing ? 'Saving…' : isNew ? 'Add item' : 'Save'}</Button>
                </div>
            </div>

            <CollapsibleCard title="Link" storageKey="menu-item:link" contentClassName="flex flex-col gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="item-type">Links to</Label>
                    <Select value={form.type} onValueChange={(v) => setForm({ ...form, type: v as ItemType })}>
                        <SelectTrigger id="item-type" className="w-full sm:w-64"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            {(Object.keys(TYPE_LABELS) as ItemType[]).map((t) => <SelectItem key={t} value={t}>{TYPE_LABELS[t]}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>

                {form.type === 'url' && (
                    <div className="grid gap-2">
                        <Label htmlFor="item-url">URL</Label>
                        <Input id="item-url" value={form.url} onChange={(e) => setForm({ ...form, url: e.target.value })} placeholder="/about or https://…" />
                        <InputError message={errors.url} />
                    </div>
                )}
                {form.type === 'entry' && (
                    <div className="grid gap-2">
                        <Label>Entry</Label>
                        <EntryPicker linkable value={form.entry} onChange={(entry) => setForm({ ...form, entry })} />
                        <InputError message={errors.target_id} />
                    </div>
                )}
                {form.type === 'collection' && (
                    <div className="grid gap-2">
                        <Label>Collection</Label>
                        <Select value={form.collectionId} onValueChange={(v) => setForm({ ...form, collectionId: v })}>
                            <SelectTrigger className="w-full"><SelectValue placeholder="Choose a collection…" /></SelectTrigger>
                            <SelectContent>
                                {collections.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.title}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        {selectedCollection && !selectedCollection.has_archive && (
                            <p className="text-xs text-amber-600">This collection has no listing page, so the item stays hidden until you enable one.</p>
                        )}
                        <InputError message={errors.target_id} />
                    </div>
                )}
                {form.type === 'term' && (
                    <div className="grid gap-2">
                        <Label>Term</Label>
                        <div className="grid gap-2 sm:grid-cols-[12rem_1fr]">
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

                <label className="flex items-center gap-2 text-sm">
                    <Checkbox checked={form.new_tab} onCheckedChange={(c) => setForm({ ...form, new_tab: !!c })} />
                    Open in a new tab
                </label>
            </CollapsibleCard>

            <CollapsibleCard
                title="Label"
                description={`Empty languages use the ${locales.main.toUpperCase()} label, then the linked page's title.`}
                storageKey="menu-item:label"
                contentClassName="flex flex-col gap-3"
            >
                {locales.available.map((lc) => (
                    <div key={lc} className="grid gap-1">
                        <div className="flex items-center gap-3">
                            <Label htmlFor={`item-label-${lc}`} className="w-8 uppercase text-muted-foreground">{lc}</Label>
                            <Input
                                id={`item-label-${lc}`}
                                value={form.labels[lc] ?? ''}
                                placeholder={form.type === 'url' ? '' : 'Uses the linked title'}
                                onChange={(e) => setForm({ ...form, labels: { ...form.labels, [lc]: e.target.value } })}
                            />
                        </div>
                        <InputError message={errors[`labels.${lc}`]} />
                    </div>
                ))}
                <InputError message={errors.labels} />
            </CollapsibleCard>

            <InputError message={errors.parent_id ?? errors.type ?? errors.new_tab} />
            <p className="text-xs text-muted-foreground">{dirty ? 'Unsaved changes' : isNew ? 'Not saved yet' : 'All changes saved'} · Ctrl/⌘ S</p>
        </form>
    );
}
