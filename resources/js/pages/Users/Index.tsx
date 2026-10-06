import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import { InputError } from '@/components/app/input-error';
import type { SharedProps } from '@/types';

interface UserRow { id: number; name: string; email: string; roles: string[]; content?: { entries: number; assets: number }; can?: { update: boolean; delete: boolean } }
interface RoleRow { id: number; name: string }

export default function UsersIndex({ users, roles }: { users: UserRow[]; roles: RoleRow[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const [open, setOpen] = React.useState(false);
    const [deleting, setDeleting] = React.useState<UserRow | null>(null);
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
                <h1 className="sunrice-page-title">Users</h1>
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
                                        onClick={() => setDeleting(u)}
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

            <DeleteUserDialog
                user={deleting}
                others={users.filter((u) => u.id !== deleting?.id)}
                onClose={() => setDeleting(null)}
                url={deleting ? adminUrl(`users/${deleting.id}`, adminPath) : ''}
            />
        </div>
    );
}

type ContentMode = 'reassign' | 'keep' | 'delete';

/** Deleting a user: choose what happens to the entries and files they created. */
function DeleteUserDialog({ user, others, url, onClose }: { user: UserRow | null; others: UserRow[]; url: string; onClose: () => void }) {
    const [mode, setMode] = React.useState<ContentMode>('reassign');
    const [target, setTarget] = React.useState('');
    const [processing, setProcessing] = React.useState(false);
    const [error, setError] = React.useState<string | undefined>();

    React.useEffect(() => {
        setMode(others.length > 0 ? 'reassign' : 'keep');
        setTarget('');
        setError(undefined);
    }, [user?.id, others.length]);

    if (!user) return null;
    const entries = user.content?.entries ?? 0;
    const assets = user.content?.assets ?? 0;
    const hasContent = entries + assets > 0;
    const plural = (n: number, one: string, many: string) => `${n} ${n === 1 ? one : many}`;
    const owned = [entries > 0 && plural(entries, 'entry', 'entries'), assets > 0 && plural(assets, 'uploaded file', 'uploaded files')].filter(Boolean).join(' and ');

    const submit = () => {
        if (hasContent && mode === 'reassign' && !target) {
            setError('Choose who receives the content.');
            return;
        }
        router.delete(url, {
            data: hasContent ? { content: mode, reassign_to: mode === 'reassign' ? target : null } : { content: 'keep' },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: onClose,
            onError: (e) => setError(e.reassign_to ?? e.content),
        });
    };

    const option = (value: ContentMode, title: string, detail: React.ReactNode, danger = false) => (
        <label className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm transition-colors ${mode === value ? (danger ? 'border-destructive/60 bg-destructive/5' : 'border-ring/60 bg-muted/40') : 'border-border hover:bg-muted/30'}`}>
            <input type="radio" name="content-mode" className="mt-0.5 accent-current" checked={mode === value} onChange={() => setMode(value)} />
            <span className="grid gap-1">
                <span className={`font-medium ${danger ? 'text-destructive' : ''}`}>{title}</span>
                <span className="text-xs text-muted-foreground">{detail}</span>
            </span>
        </label>
    );

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Delete {user.name}?</DialogTitle>
                </DialogHeader>
                <div className="grid gap-3">
                    {hasContent ? (
                        <>
                            <p className="text-sm text-muted-foreground">
                                {user.email} created {owned}. What should happen to {entries + assets === 1 ? 'it' : 'them'}?
                            </p>
                            {others.length > 0 && option('reassign', 'Move it to another user', (
                                <span className="mt-1 block" onClick={(e) => e.preventDefault()}>
                                    <Select value={target} onValueChange={(v) => { setTarget(v); setMode('reassign'); setError(undefined); }}>
                                        <SelectTrigger className="w-full bg-card"><SelectValue placeholder="Choose a user…" /></SelectTrigger>
                                        <SelectContent>
                                            {others.map((o) => <SelectItem key={o.id} value={String(o.id)}>{o.name} ({o.email})</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </span>
                            ))}
                            {option('keep', 'Keep it without an author', 'Entries and files stay as they are; "Created by" shows no one.')}
                            {entries > 0 && option('delete', `Delete their ${plural(entries, 'entry', 'entries')}`, (
                                <>Moves {entries === 1 ? 'it' : 'them'} to the trash and off the site. They can be restored from each collection's trash until it is emptied. Uploaded files are kept.</>
                            ), true)}
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground">{user.email} hasn't created any entries or files. This can't be undone.</p>
                    )}
                    <InputError message={error} />
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="button" variant="destructive" disabled={processing} onClick={submit}>
                            {processing ? 'Deleting…' : mode === 'delete' && hasContent ? 'Delete user and entries' : 'Delete user'}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
