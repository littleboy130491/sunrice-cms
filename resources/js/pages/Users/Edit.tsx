import * as React from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { InputError } from '@/components/app/input-error';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { DeleteUserDialog, type DeletableUser } from '@/components/app/delete-user-dialog';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';
import type { SharedProps } from '@/types';

interface Props {
    user: (DeletableUser & { roles: string[]; is_self: boolean }) | null;
    roles: { id: number; name: string }[];
    others?: DeletableUser[];
}

/** Create or edit a user: name, email, password and roles. */
export default function UserEdit({ user, roles, others = [] }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const isNew = user === null;
    const [deleting, setDeleting] = React.useState(false);
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
        roles: (user?.roles ?? []) as string[],
    });
    const formRef = React.useRef<HTMLFormElement>(null);
    useUnsavedChanges(form.isDirty && !form.processing, () => formRef.current?.requestSubmit());

    const listUrl = adminUrl('users', adminPath);
    useBreadcrumbs([{ label: 'Manage' }, { label: 'Users', href: listUrl }, { label: isNew ? 'New user' : user.name }]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isNew) {
            form.post(adminUrl('users', adminPath));
        } else {
            form.put(adminUrl(`users/${user.id}`, adminPath), {
                preserveScroll: true,
                onSuccess: () => {
                    form.setData((d) => ({ ...d, password: '', password_confirmation: '' }));
                    form.setDefaults({ ...form.data, password: '', password_confirmation: '' });
                },
            });
        }
    };

    const errors = form.errors as Record<string, string>;
    const rolesError = Object.entries(errors).find(([k]) => k === 'roles' || k.startsWith('roles.'))?.[1];

    return (
        <form ref={formRef} onSubmit={submit} className="flex max-w-2xl flex-col gap-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex min-w-0 items-start gap-3">
                    <Button variant="outline" size="icon" className="size-8 shrink-0" asChild>
                        <Link href={listUrl} aria-label="Back to users"><ArrowLeft /></Link>
                    </Button>
                    <div className="min-w-0 space-y-1">
                        <h1 className="truncate sunrice-page-title">{isNew ? 'New user' : user.name}</h1>
                        {!isNew && <p className="text-sm text-muted-foreground">{user.email}</p>}
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {!isNew && !user.is_self && can('sunrice.users.delete') && (
                        <Button type="button" variant="outline" size="icon" className="text-destructive" aria-label={`Delete ${user.email}`} onClick={() => setDeleting(true)}>
                            <Trash2 />
                        </Button>
                    )}
                    <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : isNew ? 'Create user' : 'Save'}</Button>
                </div>
            </div>

            <CollapsibleCard title="Account" storageKey="user:account" contentClassName="flex flex-col gap-4">
                <div className="grid gap-4 sm:grid-cols-2 items-start">
                    <div className="grid gap-2">
                        <Label htmlFor="user-name">Name</Label>
                        <Input id="user-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus={isNew} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="user-email">Email</Label>
                        <Input id="user-email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                        <InputError message={errors.email} />
                    </div>
                </div>
            </CollapsibleCard>

            <CollapsibleCard
                title="Password"
                description={isNew ? undefined : 'Leave empty to keep the current password.'}
                storageKey="user:password"
                contentClassName="grid gap-4 sm:grid-cols-2"
            >
                <div className="grid gap-2">
                    <Label htmlFor="user-password">{isNew ? 'Password' : 'New password'}</Label>
                    <Input id="user-password" type="password" autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                    <InputError message={errors.password} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="user-password-confirmation">Confirm password</Label>
                    <Input id="user-password-confirmation" type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} />
                </div>
            </CollapsibleCard>

            <CollapsibleCard title="Roles" description="What this user can see and do in the admin." storageKey="user:roles" contentClassName="flex flex-col gap-2">
                {roles.map((r) => (
                    <label key={r.id} className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.roles.includes(r.name)}
                            onCheckedChange={(c) => form.setData('roles', c ? [...form.data.roles, r.name] : form.data.roles.filter((n) => n !== r.name))}
                        />
                        {r.name}
                    </label>
                ))}
                {roles.length === 0 && <p className="text-sm text-muted-foreground">No roles yet.</p>}
                <InputError message={rolesError} />
            </CollapsibleCard>

            <p className="text-xs text-muted-foreground">{form.isDirty ? 'Unsaved changes' : isNew ? 'Not saved yet' : 'All changes saved'} · Ctrl/⌘ S</p>

            {!isNew && (
                <DeleteUserDialog
                    user={deleting ? user : null}
                    others={others}
                    onClose={() => setDeleting(false)}
                    url={adminUrl(`users/${user.id}`, adminPath)}
                />
            )}
        </form>
    );
}
