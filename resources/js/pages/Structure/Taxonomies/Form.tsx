import { useForm, usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent } from '@/components/ui/card';
import { adminUrl } from '@/lib/route';
import { InputError } from '@/components/app/input-error';
import type { SharedProps } from '@/types';

interface TaxonomyShape {
    id: number; handle: string; title: string; blueprint_id: number | null; hierarchical: boolean;
    settings: { sluggable?: boolean; route?: string; has_archive?: boolean; per_page?: number; template?: string };
    collection_ids: number[];
}

interface Props {
    taxonomy: TaxonomyShape | null;
    blueprints: { id: number; title: string }[];
    collections: { id: number; title: string; handle: string }[];
}

export default function TaxonomyForm({ taxonomy, blueprints, collections }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const form = useForm({
        title: taxonomy?.title ?? '',
        handle: taxonomy?.handle ?? '',
        blueprint_id: taxonomy?.blueprint_id ?? '',
        hierarchical: taxonomy?.hierarchical ?? false,
        settings: {
            sluggable: taxonomy?.settings?.sluggable !== false,
            route: taxonomy?.settings?.route ?? '',
            has_archive: !!taxonomy?.settings?.has_archive,
            per_page: taxonomy?.settings?.per_page ?? ('' as number | ''),
            template: taxonomy?.settings?.template ?? '',
        },
        collection_ids: taxonomy?.collection_ids ?? [],
    });
    const handle = form.data.handle || 'taxonomy';
    const attached = collections.filter((c) => form.data.collection_ids.includes(c.id));

    const errors = form.errors as Record<string, string>;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (taxonomy) {
            form.put(adminUrl(`structure/taxonomies/${taxonomy.id}`, adminPath), { preserveScroll: true });
        } else {
            form.post(adminUrl('structure/taxonomies', adminPath));
        }
    };

    return (
        <Card className="max-w-2xl">
            <CardContent>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <h1 className="text-xl font-semibold tracking-tight">{taxonomy ? `Edit ${taxonomy.title}` : 'New taxonomy'}</h1>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="title">Title</Label>
                            <Input id="title" value={form.data.title} onChange={(e) => {
                                form.setData('title', e.target.value);
                                if (!taxonomy) form.setData('handle', e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, ''));
                            }} required />
                            <InputError message={errors.title} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="handle">Handle</Label>
                            <Input id="handle" value={form.data.handle} onChange={(e) => form.setData('handle', e.target.value)} required />
                            <InputError message={errors.handle} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Blueprint</Label>
                        <Select value={String(form.data.blueprint_id || '')} onValueChange={(v) => form.setData('blueprint_id', Number(v))}>
                            <SelectTrigger><SelectValue placeholder="None" /></SelectTrigger>
                            <SelectContent>
                                {blueprints.map((b) => <SelectItem key={b.id} value={String(b.id)}>{b.title}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.blueprint_id} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={form.data.hierarchical} onCheckedChange={(c) => form.setData('hierarchical', !!c)} />
                        Hierarchical (terms have parents)
                    </label>
                    <div className="grid gap-2">
                        <Label>Collections</Label>
                        <p className="text-xs text-muted-foreground">Entries of these collections can be tagged with this taxonomy's terms.</p>
                        {collections.map((c) => (
                            <label key={c.id} className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={form.data.collection_ids.includes(c.id)}
                                    onCheckedChange={(checked) =>
                                        form.setData('collection_ids', checked
                                            ? [...form.data.collection_ids, c.id]
                                            : form.data.collection_ids.filter((id) => id !== c.id))
                                    }
                                />
                                {c.title}
                            </label>
                        ))}
                        {collections.length === 0 && <p className="text-sm text-muted-foreground">No collections yet.</p>}
                        <InputError message={errors.collection_ids ?? Object.entries(errors).find(([k]) => k.startsWith('collection_ids.'))?.[1]} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={!!form.data.settings.has_archive}
                            onCheckedChange={(c) => form.setData('settings', { ...form.data.settings, has_archive: !!c })}
                        />
                        Term archive pages (a page per term listing its entries)
                    </label>
                    {form.data.settings.has_archive && (
                        <div className="grid gap-2">
                            <Label htmlFor="route">Term route prefix (optional)</Label>
                            <Input
                                id="route"
                                className="font-mono text-sm"
                                value={form.data.settings.route}
                                placeholder={attached.length > 0 ? `/${attached[0].handle}/${handle}/{slug}` : `/${handle}/{slug}`}
                                onChange={(e) => form.setData('settings', { ...form.data.settings, route: e.target.value })}
                            />
                            <p className="text-xs text-muted-foreground">
                                {attached.length > 0 ? (
                                    <>Leave empty for one page per collection: {attached.map((c) => <code key={c.id} className="mr-1">/{c.handle}/{handle}/&#123;slug&#125;</code>)} each listing that collection's entries. </>
                                ) : (
                                    <>Leave empty for <code>/{handle}/&#123;slug&#125;</code>. </>
                                )}
                                A prefix such as <code>topics</code> gives a single page per term across all collections.
                            </p>
                            <InputError message={errors['settings.route']} />
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="per_page">Entries per page</Label>
                                    <Input id="per_page" type="number" min={1} max={100} placeholder="12" value={form.data.settings.per_page}
                                        onChange={(e) => form.setData('settings', { ...form.data.settings, per_page: e.target.value === '' ? '' : Number(e.target.value) })} />
                                    <InputError message={errors['settings.per_page']} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="template">Template</Label>
                                    <Input id="template" className="font-mono text-sm" placeholder="Automatic" value={form.data.settings.template}
                                        onChange={(e) => form.setData('settings', { ...form.data.settings, template: e.target.value })} />
                                    <InputError message={errors['settings.template']} />
                                </div>
                            </div>
                        </div>
                    )}
                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>Save</Button>
                        <Button type="button" variant="outline" onClick={() => router.get(adminUrl('structure/taxonomies', adminPath))}>Cancel</Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
