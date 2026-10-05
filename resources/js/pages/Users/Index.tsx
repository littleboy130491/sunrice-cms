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
import type { SharedProps } from '@/types';

interface UserRow { id: number; name: string; email: string; roles: string[] }
interface RoleRow { id: number; name: string }

export default function UsersIndex({ users, roles }: { users: UserRow[]; roles: RoleRow[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const [open, setOpen] = React.useState(false);
    const [editing, setEditing] = React.useState<UserRow | null>(null);
    const [form, setForm] = React.useState<{ name: string; email: string; password: string; password_confirmation: string; roles: string[] }>({
        name: '', email: '', password: '', password_confirmation: '', roles: [],
    });

    const openCreate = () => {
        setEditing(null);
        setForm({ name: '', email: '', password: '', password_confirmation: '', roles: [] });
        setOpen(true);
    };

    const openEdit = (u: UserRow) => {
        setEditing(u);
        setForm({ name: u.name, email: u.email, password: '', password_confirmation: '', roles: u.roles });
        setOpen(true);
    };

    const submit = () => {
        if (editing) {
            router.put(adminUrl(`users/${editing.id}`, adminPath), form, { preserveScroll: true, onSuccess: () => setOpen(false) });
        } else {
            router.post(adminUrl('users', adminPath), form, { onSuccess: () => setOpen(false) });
        }
    };

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Users</h1>
                <Button onClick={openCreate}><Plus className="mr-1 h-4 w-4" /> New user</Button>
            </div>
            <Table>
                <TableHeader><TableRow><TableHead>Name</TableHead><TableHead>Email</TableHead><TableHead>Roles</TableHead><TableHead className="w-24" /></TableRow></TableHeader>
                <TableBody>
                    {users.map((u) => (
                        <TableRow key={u.id}>
                            <TableCell><button type="button" className="font-medium hover:underline" onClick={() => openEdit(u)}>{u.name}</button></TableCell>
                            <TableCell>{u.email}</TableCell>
                            <TableCell>
                                <div className="flex flex-wrap gap-1">{u.roles.map((r) => <Badge key={r} variant="secondary">{r}</Badge>)}</div>
                            </TableCell>
                            <TableCell>
                                <Button
                                    variant="ghost" size="icon" className="text-destructive"
                                    onClick={() => window.confirm(`Delete ${u.email}?`) && router.delete(adminUrl(`users/${u.id}`, adminPath), { preserveScroll: true })}
                                ><Trash2 className="h-4 w-4" /></Button>
                            </TableCell>
                        </TableRow>
                    ))}
                    {users.length === 0 && <TableRow><TableCell colSpan={4} className="h-24 text-center text-muted-foreground">No users.</TableCell></TableRow>}
                </TableBody>
            </Table>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader><DialogTitle>{editing ? `Edit ${editing.name}` : 'New user'}</DialogTitle></DialogHeader>
                    <div className="flex flex-col gap-3">
                        <div className="grid gap-2"><Label>Name</Label><Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></div>
                        <div className="grid gap-2"><Label>Email</Label><Input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></div>
                        <div className="grid gap-2"><Label>{editing ? 'New password (blank = keep)' : 'Password'}</Label><Input type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></div>
                        <div className="grid gap-2"><Label>Confirm password</Label><Input type="password" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} /></div>
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
                        </div>
                        <Button onClick={submit}>Save</Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
