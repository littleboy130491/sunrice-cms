import { useForm, usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent } from '@/components/ui/card';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

interface TaxonomyShape {
    id: number; handle: string; title: string; blueprint_id: number | null; hierarchical: boolean;
    settings: { sluggable?: boolean; route?: string };
}

interface Props {
    taxonomy: TaxonomyShape | null;
    blueprints: { id: number; title: string }[];
}

export default function TaxonomyForm({ taxonomy, blueprints }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const form = useForm({
        title: taxonomy?.title ?? '',
        handle: taxonomy?.handle ?? '',
        blueprint_id: taxonomy?.blueprint_id ?? '',
        hierarchical: taxonomy?.hierarchical ?? false,
        settings: {
            sluggable: taxonomy?.settings?.sluggable !== false,
            route: taxonomy?.settings?.route ?? '',
        },
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (taxonomy) {
            form.put(adminUrl(`structure/taxonomies/${taxonomy.id}`, adminPath));
        } else {
            form.post(adminUrl('structure/taxonomies', adminPath));
        }
    };

    return (
        <Card className="max-w-2xl">
            <CardContent className="pt-6">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <h1 className="text-2xl font-semibold">{taxonomy ? `Edit ${taxonomy.title}` : 'New taxonomy'}</h1>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="title">Title</Label>
                            <Input id="title" value={form.data.title} onChange={(e) => {
                                form.setData('title', e.target.value);
                                if (!taxonomy) form.setData('handle', e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, ''));
                            }} required />
                            {form.errors.title && <p className="text-sm text-destructive">{form.errors.title}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="handle">Handle</Label>
                            <Input id="handle" value={form.data.handle} onChange={(e) => form.setData('handle', e.target.value)} required />
                            {form.errors.handle && <p className="text-sm text-destructive">{form.errors.handle}</p>}
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
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={form.data.hierarchical} onCheckedChange={(c) => form.setData('hierarchical', !!c)} />
                        Hierarchical (terms have parents)
                    </label>
                    <div className="grid gap-2">
                        <Label htmlFor="route">Term route prefix (optional)</Label>
                        <Input id="route" value={form.data.settings.route} onChange={(e) => form.setData('settings', { ...form.data.settings, route: e.target.value })} />
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>Save</Button>
                        <Button type="button" variant="outline" onClick={() => router.get(adminUrl('structure/taxonomies', adminPath))}>Cancel</Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
