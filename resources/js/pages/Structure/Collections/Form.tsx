import { useForm, usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types'; import type { Json } from '@/types';

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
    const { adminPath } = usePage<SharedProps>().props;
    const form = useForm({
        handle: collection?.handle ?? '',
        title: collection?.title ?? '',
        blueprint_id: collection?.blueprint_id ?? '',
        settings: {
            dated: !!collection?.settings?.dated,
            translatable: collection?.settings?.translatable !== false,
            sluggable: collection?.settings?.sluggable !== false,
            archivable: !!collection?.settings?.archivable,
            route: (collection?.settings?.route as string) ?? '',
        },
        taxonomy_ids: collection?.taxonomy_ids ?? [],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (collection) {
            form.put(adminUrl(`structure/collections/${collection.id}`, adminPath));
        } else {
            form.post(adminUrl('structure/collections', adminPath));
        }
    };

    return (
        <form onSubmit={submit} className="flex max-w-2xl flex-col gap-6">
            <h1 className="text-xl font-semibold tracking-tight">{collection ? `Edit ${collection.title}` : 'New collection'}</h1>
            <Card>
                <CardHeader><CardTitle>Basics</CardTitle></CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="title">Title</Label>
                        <Input id="title" value={form.data.title} onChange={(e) => {
                            form.setData('title', e.target.value);
                            if (!collection) {
                                form.setData('handle', e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, ''));
                            }
                        }} required />
                        {form.errors.title && <p className="text-sm text-destructive">{form.errors.title}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="handle">Handle</Label>
                        <Input id="handle" value={form.data.handle} onChange={(e) => form.setData('handle', e.target.value)} required />
                        {form.errors.handle && <p className="text-sm text-destructive">{form.errors.handle}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label>Blueprint</Label>
                        <Select value={String(form.data.blueprint_id || '')} onValueChange={(v) => form.setData('blueprint_id', Number(v))}>
                            <SelectTrigger><SelectValue placeholder="Choose…" /></SelectTrigger>
                            <SelectContent>
                                {blueprints.map((b) => <SelectItem key={b.id} value={String(b.id)}>{b.title}</SelectItem>)}
                            </SelectContent>
                        </Select>
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
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader><CardTitle>Settings</CardTitle></CardHeader>
                <CardContent className="flex flex-col gap-3">
                    {(['dated', 'translatable', 'sluggable', 'archivable'] as const).map((key) => (
                        <label key={key} className="flex items-center gap-3 text-sm capitalize">
                            <Checkbox
                                checked={!!form.data.settings[key]}
                                onCheckedChange={(c) => form.setData('settings', { ...form.data.settings, [key]: !!c })}
                            />
                            {key}
                        </label>
                    ))}
                    <div className="grid gap-2">
                        <Label htmlFor="route">Route prefix (e.g. blog/…)</Label>
                        <Input id="route" value={form.data.settings.route} onChange={(e) => form.setData('settings', { ...form.data.settings, route: e.target.value })} />
                    </div>
                </CardContent>
            </Card>
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="outline" onClick={() => router.get(adminUrl('structure/collections', adminPath))}>Cancel</Button>
            </div>
        </form>
    );
}
