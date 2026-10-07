import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { InputError } from '@/components/app/input-error';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

/** New menu: a title and the handle templates use (<x-sunrice::menu handle="…">). */
export default function MenuCreate() {
    const { adminPath } = usePage<SharedProps>().props;
    const form = useForm({ title: '', handle: '' });
    const listUrl = adminUrl('menus', adminPath);
    useBreadcrumbs([{ label: 'Structure' }, { label: 'Menus', href: listUrl }, { label: 'New menu' }]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(adminUrl('menus', adminPath));
    };

    return (
        <form onSubmit={submit} className="flex max-w-2xl flex-col gap-6">
            <div className="flex items-start justify-between gap-4">
                <div className="flex items-start gap-3">
                    <Button variant="outline" size="icon" className="size-8 shrink-0" asChild>
                        <Link href={listUrl} aria-label="Back to menus"><ArrowLeft /></Link>
                    </Button>
                    <h1 className="sunrice-page-title">New menu</h1>
                </div>
                <Button type="submit" disabled={form.processing}>{form.processing ? 'Creating…' : 'Create menu'}</Button>
            </div>
            <CollapsibleCard title="Menu" storageKey="menu:create" contentClassName="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="menu-title">Title</Label>
                    <Input
                        id="menu-title"
                        autoFocus
                        placeholder="e.g. Main navigation"
                        value={form.data.title}
                        onChange={(e) => form.setData({
                            title: e.target.value,
                            handle: e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, ''),
                        })}
                    />
                    <InputError message={form.errors.title} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="menu-handle">Handle</Label>
                    <Input id="menu-handle" className="font-mono" value={form.data.handle} onChange={(e) => form.setData('handle', e.target.value)} />
                    <InputError message={form.errors.handle} />
                    <p className="text-xs text-muted-foreground">Templates load it with <code>{`sunrice_menu('${form.data.handle || 'main'}')`}</code>.</p>
                </div>
            </CollapsibleCard>
        </form>
    );
}
