import * as React from 'react';
import { useForm } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { Image as ImageIcon, LoaderCircle, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AssetPicker, { PickedAsset } from '@/components/AssetPicker';
import { InputError } from '@/components/app/input-error';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { SettingsTabs } from '@/components/app/settings-tabs';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';

type Role = 'title' | 'description' | 'image';
interface Field { handle: string; label: string; type: string }
interface Row {
    kind: 'collection' | 'taxonomy';
    id: number;
    title: string;
    blueprint: string | null;
    fields: Record<Role, Field[]>;
    chosen: Record<Role, string>;
    resolved: Record<Role, string | null>;
}

interface SiteSeo { noindex: boolean; twitter_site: string | null; image: number | null; title_suffix: boolean; title_separator: string | null }

interface Props {
    collections: Row[];
    taxonomies: Row[];
    site: SiteSeo;
    siteName: string;
    shareImage: { id: number; url: string; filename: string } | null;
}

const ROLES: { role: Role; label: string; hint: string }[] = [
    { role: 'title', label: 'Meta title', hint: 'Automatic uses the title.' },
    { role: 'description', label: 'Meta description', hint: 'Text is cut to 160 characters.' },
    { role: 'image', label: 'Share image', hint: 'The first image of the field.' },
];

export default function SeoSettings({ collections, taxonomies, site, siteName, shareImage }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const [image, setImage] = React.useState(shareImage);
    const form = useForm({
        seo: {
            noindex: !!site.noindex,
            twitter_site: site.twitter_site ?? '',
            image: site.image ?? null as number | null,
            title_suffix: !!site.title_suffix,
            title_separator: site.title_separator ?? '|',
        },
        items: [...collections, ...taxonomies].map((r) => ({ kind: r.kind, id: r.id, chosen: { ...r.chosen } })),
    });
    const errors = form.errors as Record<string, string>;
    const rows = [...collections, ...taxonomies];

    const setChoice = (index: number, role: Role, value: string) =>
        form.setData('items', form.data.items.map((item, i) => (i === index ? { ...item, chosen: { ...item.chosen, [role]: value } } : item)));

    const labelOf = (row: Row, role: Role, handle: string | null) =>
        handle === null ? (role === 'title' ? 'the title' : 'nothing') : row.fields[role].find((f) => f.handle === handle)?.label ?? handle;

    const section = (title: string, description: string, list: Row[], offset: number) =>
        list.length > 0 && (
            <CollapsibleCard title={title} description={description} storageKey={`settings:seo:${title.toLowerCase()}`} contentClassName="flex flex-col divide-y">
                {list.map((row, i) => {
                    const index = offset + i;
                    return (
                        <div key={`${row.kind}-${row.id}`} className="grid gap-3 py-4 first:pt-0 last:pb-0">
                            <div className="flex items-baseline justify-between gap-2">
                                <span className="font-medium">{row.title}</span>
                                <span className="text-xs text-muted-foreground">{row.blueprint ? `Blueprint: ${row.blueprint}` : 'No blueprint'}</span>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-3">
                                {ROLES.map(({ role, label }) => {
                                    const value = form.data.items[index].chosen[role] || 'auto';
                                    return (
                                        <div key={role} className="grid gap-1.5">
                                            <span className="text-xs font-medium text-muted-foreground">{label}</span>
                                            <Select value={value} onValueChange={(v) => setChoice(index, role, v === 'auto' ? '' : v)}>
                                                <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="auto">{row.chosen[role] ? 'Automatic' : `Automatic (${labelOf(row, role, row.resolved[role])})`}</SelectItem>
                                                    <SelectItem value="none">None</SelectItem>
                                                    {row.fields[role].map((f) => <SelectItem key={f.handle} value={f.handle}>{f.label}</SelectItem>)}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}
            </CollapsibleCard>
        );

    // Leave-page warning for unsaved edits; Ctrl/⌘ S saves.
    const formRef = React.useRef<HTMLFormElement>(null);
    useUnsavedChanges(form.isDirty && !form.processing, () => formRef.current?.requestSubmit());

    return (
        <form ref={formRef}
            onSubmit={(e) => { e.preventDefault(); form.put(adminUrl('settings/seo', adminPath), { preserveScroll: true }); }}
            className="flex max-w-4xl flex-col gap-6"
        >
            <div className="flex items-center justify-between">
                <h1 className="sunrice-page-title">Settings</h1>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />} Save SEO settings
                </Button>
            </div>
            <SettingsTabs current="seo" />
            <CollapsibleCard title="Search engines & sharing" storageKey="settings:search-engines-sharing" hasErrors={Object.keys(errors).some((k) => k.startsWith('seo.'))} contentClassName="flex flex-col gap-4">
                <label className="flex items-start gap-3">
                    <Switch checked={form.data.seo.noindex} onCheckedChange={(v) => form.setData('seo', { ...form.data.seo, noindex: v })} />
                    <span className="grid gap-0.5 text-sm">
                        <span className="font-medium">Hide the whole site from search engines</span>
                        <span className="text-xs text-muted-foreground">Adds noindex to every page and empties the sitemap. Useful on staging.</span>
                    </span>
                </label>
                <div className="flex flex-wrap items-start gap-3">
                    <label className="flex flex-1 items-start gap-3">
                        <Switch checked={form.data.seo.title_suffix} onCheckedChange={(v) => form.setData('seo', { ...form.data.seo, title_suffix: v })} />
                        <span className="grid gap-0.5 text-sm">
                            <span className="font-medium">Add the site name to page titles</span>
                            <span className="text-xs text-muted-foreground">
                                The browser tab and search results show “About us {form.data.seo.title_separator.trim() || '|'} {siteName || 'Site name'}”. Share previews keep the bare title.
                            </span>
                        </span>
                    </label>
                    {form.data.seo.title_suffix && (
                        <div className="grid w-24 gap-1.5">
                            <Label htmlFor="title-separator" className="text-xs">Separator</Label>
                            <Input id="title-separator" maxLength={5} value={form.data.seo.title_separator} onChange={(e) => form.setData('seo', { ...form.data.seo, title_separator: e.target.value })} />
                        </div>
                    )}
                </div>
                <InputError message={errors['seo.title_separator']} />
                <div className="grid gap-2 sm:max-w-xs">
                    <Label htmlFor="twitter-site">X/Twitter handle</Label>
                    <Input id="twitter-site" placeholder="@acme" value={form.data.seo.twitter_site} onChange={(e) => form.setData('seo', { ...form.data.seo, twitter_site: e.target.value })} />
                    <InputError message={errors['seo.twitter_site']} />
                </div>
                <div className="grid gap-2">
                    <Label>Default share image</Label>
                    <div className="flex items-center gap-3">
                        {image ? (
                            <div className="flex items-center gap-2 rounded-md border p-2 text-sm">
                                <img src={image.url} alt="" className="size-10 rounded object-cover" />
                                <span className="max-w-48 truncate">{image.filename}</span>
                                <button type="button" className="text-muted-foreground hover:text-foreground" aria-label="Remove image"
                                    onClick={() => { setImage(null); form.setData('seo', { ...form.data.seo, image: null }); }}>
                                    <X className="size-4" />
                                </button>
                            </div>
                        ) : (
                            <span className="flex items-center gap-2 text-sm text-muted-foreground"><ImageIcon className="size-4" /> None</span>
                        )}
                        <AssetPicker
                            imageOnly
                            trigger={<Button type="button" variant="outline" size="sm">Choose image</Button>}
                            onSelect={(assets: PickedAsset[]) => {
                                const a = assets[0];
                                if (!a) return;
                                setImage({ id: a.id, url: a.url, filename: a.filename });
                                form.setData('seo', { ...form.data.seo, image: a.id });
                            }}
                        />
                    </div>
                    <InputError message={errors['seo.image']} />
                    <p className="text-xs text-muted-foreground">Shown when a page is shared and has no image of its own.</p>
                </div>
            </CollapsibleCard>

            <div className="grid gap-1.5">
                <h2 className="text-base font-semibold">Fields used for SEO</h2>
                <p className="text-sm text-muted-foreground">
                    When an entry or term leaves its meta title, description or share image empty, the site uses one of its fields
                    instead. <strong>Automatic</strong> picks a fitting field (e.g. <code>excerpt</code> or <code>summary</code> for the
                    description, the first image field for the share image). The page's own SEO fields always win; after the field come
                    the collection's or taxonomy's defaults (Structure) and then the site's (default description in General, share image above).
                </p>
            </div>
            {rows.length === 0 && <p className="text-sm text-muted-foreground">No collections with pages or taxonomies with term pages yet.</p>}
            {section('Collections', 'Entries of each collection.', collections, 0)}
            {section('Taxonomies', 'Term pages of each taxonomy.', taxonomies, collections.length)}
            <p className="text-xs text-muted-foreground">{ROLES.map((r) => `${r.label}: ${r.hint}`).join(' ')}</p>
        </form>
    );
}
