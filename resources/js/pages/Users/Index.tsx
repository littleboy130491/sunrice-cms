import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';
import { DeleteUserDialog } from '@/components/app/delete-user-dialog';

interface UserRow { id: number; name: string; email: string; roles: string[]; content?: { entries: number; assets: number }; can?: { update: boolean; delete: boolean } }

export default function UsersIndex({ users }: { users: UserRow[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const [deleting, setDeleting] = React.useState<UserRow | null>(null);
    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="sunrice-page-title">Users</h1>
                {can('sunrice.users.create') && (
                    <Button asChild><Link href={adminUrl('users/create', adminPath)}><Plus className="mr-1 h-4 w-4" /> New user</Link></Button>
                )}
            </div>
            <Table>
                <TableHeader><TableRow><TableHead>Name</TableHead><TableHead className="max-md:hidden">Email</TableHead><TableHead className="max-md:hidden">Roles</TableHead><TableHead className="w-24" /></TableRow></TableHeader>
                <TableBody>
                    {users.map((u) => (
                        <TableRow key={u.id}>
                            <TableCell>
                                {(u.can?.update ?? can('sunrice.users.edit')) ? <Link className="font-medium hover:underline" href={adminUrl(`users/${u.id}/edit`, adminPath)}>{u.name}</Link> : <span className="font-medium">{u.name}</span>}
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

            <DeleteUserDialog
                user={deleting}
                others={users.filter((u) => u.id !== deleting?.id)}
                onClose={() => setDeleting(null)}
                url={deleting ? adminUrl(`users/${deleting.id}`, adminPath) : ''}
            />
        </div>
    );
}
