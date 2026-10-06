import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import { InputError } from '@/components/app/input-error';
import type { SharedProps } from '@/types';

interface UserRow { id: number; name: string; email: string; roles: string[]; can?: { update: boolean; delete: boolean } }
interface RoleRow { id: number; name: string }

export default function UsersIndex({ users, roles }: { users: UserRow[]; roles: RoleRow[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const [open, setOpen] = React.useState(false);
    const [editing, setEditing] = React.useState<UserRow | null>(null);
    const [form, setForm] = React.useState<{ name: string; email: string; password: string; password_confirmation: string; roles: string[] }>({
        name: '', email: '', password: '', password_confirmation: '', roles: [],
    });

    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [processing, setProcessing] = React.useState(false);

    const openCreate = () => {
        setErrors({});
        setEditing(null);
        setForm({ name: '', email: '', password: '', password_confirmation: '', roles: [] });
        setOpen(true);
    };

    const openEdit = (u: UserRow) => {
        setErrors({});
        setEditing(u);
        setForm({ name: u.name, email: u.email, password: '', password_confirmation: '', roles: u.roles });
        setOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => { setOpen(false); setErrors({}); },
            onError: (e: Record<string, string>) => setErrors(e),
        };
        if (editing) {
            router.put(adminUrl(`users/${editing.id}`, adminPath), form, options);
        } else {
            router.post(adminUrl('users', adminPath), form, options);
        }
    };
    const rolesError = Object.entries(errors).find(([k]) => k === 'roles' || k.startsWith('roles.'))?.[1];

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold tracking-tight">Users</h1>
                {can('sunrice.users.create') && <Button onClick={openCreate}><Plus className="mr-1 h-4 w-4" /> New user</Button>}
            </div>
            <Table>
                <TableHeader><TableRow><TableHead>Name</TableHead><TableHead className="max-md:hidden">Email</TableHead><TableHead className="max-md:hidden">Roles</TableHead><TableHead className="w-24" /></TableRow></TableHeader>
                <TableBody>
                    {users.map((u) => (
                        <TableRow key={u.id}>
                            <TableCell>
                                {(u.can?.update ?? can('sunrice.users.edit')) ? <button type="button" className="font-medium hover:underline" onClick={() => openEdit(u)}>{u.name}</button> : <span className="font-medium">{u.name}</span>}
                                {/* On phones the email sits under the name. */}
                                <div className="text-xs text-muted-foreground md:hidden">{u.email}</div>
                            </TableCell>
                            <TableCell className="max-md:hidden">{u.email}</TableCell>
                            <TableCell className="max-md:hidden">
                                <div className="flex flex-wrap gap-1">{u.roles.map((r) => <Badge key={r} variant="secondary">{r}</Badge>)}</div>
                            </TableCell>
                            <TableCell>
                                {(u.can?.delete ?? can('sunrice.users.delete')) && (
                                    <Button
                                        variant="ghost" size="icon" className="text-destructive" aria-label={`Delete ${u.email}`}
                                        onClick={() => window.confirm(`Delete ${u.email}?`) && router.delete(adminUrl(`users/${u.id}`, adminPath), { preserveScroll: true })}
                                    ><Trash2 className="h-4 w-4" /></Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                    {users.length === 0 && <TableRow><TableCell colSpan={4} className="h-24 text-center text-muted-foreground">No users.</TableCell></TableRow>}
                </TableBody>
            </Table>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader><DialogTitle>{editing ? `Edit ${editing.name}` : 'New user'}</DialogTitle></DialogHeader>
                    <form onSubmit={submit} className="flex flex-col gap-3">
                        <div className="grid gap-2"><Label htmlFor="user-name">Name</Label><Input id="user-name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /><InputError message={errors.name} /></div>
                        <div className="grid gap-2"><Label htmlFor="user-email">Email</Label><Input id="user-email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /><InputError message={errors.email} /></div>
                        <div className="grid gap-2"><Label htmlFor="user-password">{editing ? 'New password (blank = keep)' : 'Password'}</Label><Input id="user-password" type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /><InputError message={errors.password} /></div>
                        <div className="grid gap-2"><Label htmlFor="user-password-confirmation">Confirm password</Label><Input id="user-password-confirmation" type="password" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} /></div>
                        <div className="grid gap-1">
                            <Label>Roles</Label>
                            {roles.map((r) => (
                                <label key={r.id} className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={form.roles.includes(r.name)}
                                        onCheckedChange={(c) => setForm({ ...form, roles: c ? [...form.roles, r.name] : form.roles.filter((n) => n !== r.name) })}
                                    />
                                    {r.name}
                                </label>
                            ))}
                            <InputError message={rolesError} />
                        </div>
                        <Button type="submit" disabled={processing}>{processing ? 'Saving…' : 'Save'}</Button>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
