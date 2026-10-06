import { useForm, usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { adminUrl } from '@/lib/route';
import { navIcon, navIconNames } from '@/components/app/nav-icon';
import { InputError } from '@/components/app/input-error';
import TranslatedTitles from '@/components/TranslatedTitles';
import type { Json, SharedProps } from '@/types';

interface CollectionShape {
    id: number;
    handle: string;
    title: string;
    blueprint_id: number | null;
    settings: Record<string, Json>;
    taxonomy_ids: number[];
}

interface Props {
    collection: CollectionShape | null;
    blueprints: { id: number; title: string }[];
    taxonomies: { id: number; title: string }[];
}

export default function CollectionsForm({ collection, blueprints, taxonomies }: Props) {
    const { adminPath, locales } = usePage<SharedProps>().props;
    const form = useForm({
        handle: collection?.handle ?? '',
        title: collection?.title ?? '',
        blueprint_id: collection?.blueprint_id ?? '',
        settings: {
            translatable: collection?.settings?.translatable !== false,
            has_single: collection?.settings?.has_single !== false,
            route: (collection?.settings?.route as string) ?? '',
            has_archive: !!collection?.settings?.has_archive,
            archive_route: (collection?.settings?.archive_route as string) ?? '',
            per_page: (collection?.settings?.per_page as number | undefined) ?? '',
            template: (collection?.settings?.template as string) ?? '',
            archive_template: (collection?.settings?.archive_template as string) ?? '',
            icon: (collection?.settings?.icon as string) ?? 'file-text',
            sort: (collection?.settings?.sort as string) ?? 'published_at',
            sort_direction: (collection?.settings?.sort_direction as string) ?? '',
            archive_blueprint_id: (collection?.settings?.archive_blueprint_id as number | undefined) ?? ('' as number | ''),
            titles: ((collection?.settings?.titles ?? {}) as Record<string, string>),
        },
        taxonomy_ids: collection?.taxonomy_ids ?? [],
    });

    const errors = form.errors as Record<string, string>;
    const settings = form.data.settings;
    const setSetting = (key: keyof typeof settings, value: Json) => form.setData('settings', { ...settings, [key]: value });
    const handle = form.data.handle || 'handle';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (collection) {
            form.put(adminUrl(`structure/collections/${collection.id}`, adminPath), { preserveScroll: true });
        } else {
            form.post(adminUrl('structure/collections', adminPath));
        }
    };

    return (
        <form onSubmit={submit} className="flex max-w-2xl flex-col gap-6">
            <h1 className="sunrice-page-title">{collection ? `Edit ${collection.title}` : 'New collection'}</h1>
            <CollapsibleCard title="Basics" storageKey="collection:basics" hasErrors={Object.keys(errors).length > 0} contentClassName="flex flex-col gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="title">Title</Label>
                    <Input id="title" value={form.data.title} onChange={(e) => {
                        form.setData('title', e.target.value);
                        if (!collection) {
                            form.setData('handle', e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, ''));
                        }
                    }} required />
                    <InputError message={errors.title} />
                </div>
                <TranslatedTitles
                    value={settings.titles}
                    onChange={(titles) => form.setData('settings', { ...settings, titles })}
                    mainTitle={form.data.title}
                    errors={errors}
                    errorPrefix="settings.titles"
                />
                <div className="grid gap-2">
                    <Label htmlFor="handle">Handle</Label>
                    <Input id="handle" value={form.data.handle} onChange={(e) => form.setData('handle', e.target.value)} required />
                    <InputError message={errors.handle} />
                </div>
                <div className="grid gap-2">
                    <Label>Sidebar icon</Label>
                    <Select value={form.data.settings.icon} onValueChange={(v) => form.setData('settings', { ...form.data.settings, icon: v })}>
                        <SelectTrigger className="w-56"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            {navIconNames.map((name) => {
                                const Icon = navIcon(name);
                                return (
                                    <SelectItem key={name} value={name}>
                                        <Icon className="size-4" /> {name.replace(/-/g, ' ')}
                                    </SelectItem>
                                );
                            })}
                        </SelectContent>
                    </Select>
                    <InputError message={errors['settings.icon']} />
                </div>
                <div className="grid gap-2">
                    <Label>Blueprint</Label>
                    <Select value={String(form.data.blueprint_id || '')} onValueChange={(v) => form.setData('blueprint_id', Number(v))}>
                        <SelectTrigger><SelectValue placeholder="Choose…" /></SelectTrigger>
                        <SelectContent>
                            {blueprints.map((b) => <SelectItem key={b.id} value={String(b.id)}>{b.title}</SelectItem>)}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.blueprint_id} />
                </div>
                <div className="grid gap-2">
                    <Label>Taxonomies</Label>
                    {taxonomies.map((t) => (
                        <label key={t.id} className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.taxonomy_ids.includes(t.id)}
                                onCheckedChange={(c) =>
                                    form.setData('taxonomy_ids', c ? [...form.data.taxonomy_ids, t.id] : form.data.taxonomy_ids.filter((id) => id !== t.id))
                                }
                            />
                            {t.title}
                        </label>
                    ))}
                    <InputError message={errors.taxonomy_ids ?? Object.entries(errors).find(([k]) => k.startsWith('taxonomy_ids.'))?.[1]} />
                </div>
            </CollapsibleCard>
            <CollapsibleCard title="Order" storageKey="collection:order" hasErrors={Object.keys(errors).length > 0} contentClassName="flex flex-col gap-3">
                <p className="text-sm text-muted-foreground">
                    How entries are listed on the site (listing page, <code>&lt;x-sunrice::entries&gt;</code> without <code>order-by</code>) and in the admin.
                </p>
                <div className="flex flex-wrap gap-3">
                    <Select value={settings.sort} onValueChange={(v) => setSetting('sort', v)}>
                        <SelectTrigger className="w-56"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="manual">Manual (drag and drop)</SelectItem>
                            <SelectItem value="published_at">Published date</SelectItem>
                            <SelectItem value="created_at">Created date</SelectItem>
                            <SelectItem value="updated_at">Last updated</SelectItem>
                            <SelectItem value="title">Title</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select value={settings.sort_direction || 'default'} onValueChange={(v) => setSetting('sort_direction', v === 'default' ? '' : v)}>
                        <SelectTrigger className="w-48"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="default">{settings.sort === 'manual' || settings.sort === 'title' ? 'First to last (A–Z)' : 'Newest first'}</SelectItem>
                            <SelectItem value="asc">Ascending</SelectItem>
                            <SelectItem value="desc">Descending</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                {settings.sort === 'manual' && (
                    <p className="text-xs text-muted-foreground">Drag entries into order on the collection's entries list. New entries go to the end.</p>
                )}
                <InputError message={errors['settings.sort'] ?? errors['settings.sort_direction']} />
            </CollapsibleCard>
            <CollapsibleCard title="Pages &amp; URLs" storageKey="collection:pages-amp-urls" hasErrors={Object.keys(errors).length > 0} contentClassName="flex flex-col gap-4">
                <label className="flex items-center gap-3 text-sm">
                    <Checkbox checked={settings.translatable} onCheckedChange={(c) => setSetting('translatable', !!c)} />
                    Translatable (entries can have a version in each language)
                </label>

                <label className="flex items-center gap-3 text-sm">
                    <Checkbox checked={settings.has_single} onCheckedChange={(c) => setSetting('has_single', !!c)} />
                    Each entry has its own page
                </label>
                {!settings.has_single && (
                    <p className="pl-7 text-xs text-muted-foreground">
                        Entries have no URL: use the collection as a list of information (team members, FAQs, partners…) shown by
                        templates with <code>&lt;x-sunrice::entries collection="{handle}"&gt;</code>. Menus and links can't point to them.
                    </p>
                )}
                {settings.has_single && (
                    <div className="grid gap-2 pl-7">
                        <Label htmlFor="route">Entry URL</Label>
                        <Input
                            id="route"
                            className="font-mono text-sm"
                            value={settings.route}
                            placeholder={`/${handle}/{slug}`}
                            onChange={(e) => setSetting('route', e.target.value)}
                        />
                        <p className="text-xs text-muted-foreground">
                            Leave empty to use the handle (<code>/{handle}/&#123;slug&#125;</code>). Type a prefix such as{' '}
                            <code>blog</code>, a full pattern such as <code>/news/&#123;slug&#125;</code>, or <code>/</code> for the site root. Each collection needs its own.
                        </p>
                        <InputError message={errors['settings.route']} />
                        <Label htmlFor="template">Entry template</Label>
                        <Input id="template" className="font-mono text-sm" value={settings.template} placeholder="Automatic" onChange={(e) => setSetting('template', e.target.value)} />
                        <InputError message={errors['settings.template']} />
                    </div>
                )}

                <label className="flex items-center gap-3 text-sm">
                    <Checkbox checked={settings.has_archive} onCheckedChange={(c) => setSetting('has_archive', !!c)} />
                    Has a listing page (archive)
                </label>
                {settings.has_archive && (
                    <div className="grid gap-2 pl-7">
                        <Label>Listing blueprint</Label>
                        <Select value={String(settings.archive_blueprint_id || 'none')} onValueChange={(v) => setSetting('archive_blueprint_id', v === 'none' ? '' : Number(v))}>
                            <SelectTrigger className="w-full sm:w-80"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">None (heading and intro only)</SelectItem>
                                {blueprints.map((b) => <SelectItem key={b.id} value={String(b.id)}>{b.title}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            Extra fields for the listing page, such as a hero image or description. Edit them, with the heading and intro,
                            from the collection's entries list (Listing page).
                        </p>
                        <InputError message={errors['settings.archive_blueprint_id']} />
                        <Label htmlFor="archive_route">Listing URL</Label>
                        <Input id="archive_route" className="font-mono text-sm" value={settings.archive_route} placeholder={`/${handle}`} onChange={(e) => setSetting('archive_route', e.target.value)} />
                        <InputError message={errors['settings.archive_route']} />
                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="per_page">Entries per page</Label>
                                <Input id="per_page" type="number" min={1} max={100} value={settings.per_page} placeholder="12"
                                    onChange={(e) => setSetting('per_page', e.target.value === '' ? '' : Number(e.target.value))} />
                                <InputError message={errors['settings.per_page']} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="archive_template">Listing template</Label>
                                <Input id="archive_template" className="font-mono text-sm" value={settings.archive_template} placeholder="Automatic"
                                    onChange={(e) => setSetting('archive_template', e.target.value)} />
                                <InputError message={errors['settings.archive_template']} />
                            </div>
                        </div>
                    </div>
                )}
            </CollapsibleCard>
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="outline" onClick={() => router.get(adminUrl('structure/collections', adminPath))}>Cancel</Button>
            </div>
        </form>
    );
}
