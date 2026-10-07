import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { adminUrl } from '@/lib/route';
import { useForm } from '@inertiajs/react';
import type { SharedProps } from '@/types';

interface Group { group: string; permissions: { name: string; label: string }[] }

export default function RoleEdit({ role, permissionGroups }: { role: { id: number; name: string; permissions: string[]; editable?: boolean } | null; permissionGroups: Group[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const isNew = role === null;
    const form = useForm({ name: role?.name ?? '', permissions: role?.permissions ?? ([] as string[]) });
    const editable = role?.editable !== false;
    const formRef = React.useRef<HTMLFormElement>(null);
    useUnsavedChanges(editable && form.isDirty && !form.processing, () => formRef.current?.requestSubmit());
    const listUrl = adminUrl('roles', adminPath);
    useBreadcrumbs([{ label: 'Manage' }, { label: 'Roles', href: listUrl }, { label: isNew ? 'New role' : role.name }]);

    const toggle = (name: string) =>
        form.setData('permissions', form.data.permissions.includes(name)
            ? form.data.permissions.filter((p) => p !== name)
            : [...form.data.permissions, name]);

    const toggleGroup = (g: Group) => {
        const all = g.permissions.map((p) => p.name);
        const checked = all.every((n) => form.data.permissions.includes(n));
        form.setData('permissions', checked
            ? form.data.permissions.filter((p) => !all.includes(p))
            : [...new Set([...form.data.permissions, ...all])]);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editable) return;
        if (isNew) {
            form.post(adminUrl('roles', adminPath));
        } else {
            form.put(adminUrl(`roles/${role.id}`, adminPath), { preserveScroll: true, onSuccess: () => form.setDefaults() });
        }
    };
    const permissionErrors = Object.entries(form.errors as Record<string, string>)
        .filter(([key]) => key === 'permissions' || key.startsWith('permissions.'))
        .map(([, message]) => message);

    return (
        <form ref={formRef} onSubmit={submit} className="flex max-w-3xl flex-col gap-4">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex min-w-0 items-start gap-3">
                    <Button variant="outline" size="icon" className="size-8 shrink-0" asChild>
                        <Link href={listUrl} aria-label="Back to roles"><ArrowLeft /></Link>
                    </Button>
                    <h1 className="truncate sunrice-page-title">{isNew ? 'New role' : editable ? `Edit ${role.name}` : role.name}</h1>
                </div>
                {editable && <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : isNew ? 'Create role' : 'Save role'}</Button>}
            </div>
            {!editable && <p className="text-sm text-muted-foreground">This role can't be edited. Super admins always have every permission.</p>}
            <div className="grid max-w-sm gap-2">
                <Label htmlFor="role-name">Name</Label>
                <Input id="role-name" autoFocus={isNew} placeholder="e.g. editor" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required disabled={!editable} />
                {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
            </div>
            {permissionErrors.length > 0 && (
                <div className="rounded-md border border-destructive/50 p-3 text-sm text-destructive">
                    {[...new Set(permissionErrors)].map((m) => <p key={m}>{m}</p>)}
                </div>
            )}
            <div className="grid gap-4 md:grid-cols-2">
                {permissionGroups.map((g) => (
                    <CollapsibleCard key={g.group} title={g.group} storageKey={`role:${g.group}`} titleClassName="text-sm" contentClassName="flex flex-col gap-2" hasErrors={permissionErrors.length > 0}
                        headerAction={editable && (
                            <button type="button" className="text-xs text-muted-foreground hover:underline" onClick={() => toggleGroup(g)}>
                                toggle all
                            </button>
                        )}>
                        {g.permissions.map((p) => (
                            <label key={p.name} className="flex items-center gap-2 text-sm">
                                <Checkbox checked={form.data.permissions.includes(p.name)} onCheckedChange={() => toggle(p.name)} disabled={!editable} />
                                <span>{p.label}</span>
                                <code className="ml-auto text-[10px] text-muted-foreground">{p.name}</code>
                            </label>
                        ))}
                    </CollapsibleCard>
                ))}
            </div>
            {editable && <p className="text-xs text-muted-foreground">{form.isDirty ? 'Unsaved changes' : isNew ? 'Not saved yet' : 'All changes saved'} · Ctrl/⌘ S</p>}
        </form>
    );
}
