import * as React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import { InputError } from '@/components/app/input-error';
import type { SharedProps } from '@/types';

interface Row { id: number; name: string; permissions_count: number }

export default function RolesIndex({ roles }: { roles: Row[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const [open, setOpen] = React.useState(false);
    const [name, setName] = React.useState('');
    const [error, setError] = React.useState<string>();
    const [processing, setProcessing] = React.useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(adminUrl('roles', adminPath), { name }, {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => { setOpen(false); setName(''); setError(undefined); },
            onError: (errors) => setError(errors.name),
        });
    };

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold tracking-tight">Roles</h1>
                {can('sunrice.roles.create') && (
                    <Dialog open={open} onOpenChange={(o) => { setOpen(o); setError(undefined); }}>
                        <DialogTrigger asChild><Button><Plus className="mr-1 h-4 w-4" /> New role</Button></DialogTrigger>
                        <DialogContent>
                            <DialogHeader><DialogTitle>New role</DialogTitle></DialogHeader>
                            <form onSubmit={submit} className="flex flex-col gap-3">
                                <div className="grid gap-2">
                                    <Label>Name</Label>
                                    <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="editor" autoFocus />
                                    <InputError message={error} />
                                </div>
                                <Button type="submit" disabled={processing}>Create</Button>
                            </form>
                        </DialogContent>
                    </Dialog>
                )}
            </div>
            <Table>
                <TableHeader><TableRow><TableHead>Name</TableHead><TableHead>Permissions</TableHead><TableHead className="w-24" /></TableRow></TableHeader>
                <TableBody>
                    {roles.map((r) => (
                        <TableRow key={r.id}>
                            <TableCell><Link className="font-medium hover:underline" href={adminUrl(`roles/${r.id}/edit`, adminPath)}>{r.name}</Link></TableCell>
                            <TableCell>{r.permissions_count}</TableCell>
                            <TableCell>
                                {can('sunrice.roles.delete') && (
                                    <Button
                                        variant="ghost" size="sm" className="text-destructive"
                                        onClick={() => window.confirm(`Delete role "${r.name}"?`) && router.delete(adminUrl(`roles/${r.id}`, adminPath), { preserveScroll: true })}
                                    >Delete</Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                    {roles.length === 0 && <TableRow><TableCell colSpan={3} className="h-24 text-center text-muted-foreground">No roles.</TableCell></TableRow>}
                </TableBody>
            </Table>
        </div>
    );
}
