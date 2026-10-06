import { Link, router, usePage } from '@inertiajs/react';
import * as React from 'react';
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

interface Row { id: number; handle: string; title: string; items_count: number }

export default function MenusIndex({ menus }: { menus: Row[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const [open, setOpen] = React.useState(false);
    const [form, setForm] = React.useState({ handle: '', title: '' });
    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [processing, setProcessing] = React.useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(adminUrl('menus', adminPath), form, {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => { setOpen(false); setForm({ handle: '', title: '' }); setErrors({}); },
            onError: setErrors,
        });
    };

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold tracking-tight">Menus</h1>
                {can('sunrice.menus.create') && (
                    <Dialog open={open} onOpenChange={(o) => { setOpen(o); setErrors({}); }}>
                        <DialogTrigger asChild><Button><Plus className="mr-1 h-4 w-4" /> New menu</Button></DialogTrigger>
                        <DialogContent>
                            <DialogHeader><DialogTitle>New menu</DialogTitle></DialogHeader>
                            <form onSubmit={submit} className="flex flex-col gap-3">
                                <div className="grid gap-2">
                                    <Label>Title</Label>
                                    <Input value={form.title} onChange={(e) => setForm({
                                        title: e.target.value,
                                        handle: e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, ''),
                                    })} />
                                    <InputError message={errors.title} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Handle</Label>
                                    <Input value={form.handle} onChange={(e) => setForm({ ...form, handle: e.target.value })} />
                                    <InputError message={errors.handle} />
                                </div>
                                <Button type="submit" disabled={processing}>Create</Button>
                            </form>
                        </DialogContent>
                    </Dialog>
                )}
            </div>
            <Table>
                <TableHeader><TableRow><TableHead>Title</TableHead><TableHead className="max-md:hidden">Handle</TableHead><TableHead>Items</TableHead><TableHead className="w-24" /></TableRow></TableHeader>
                <TableBody>
                    {menus.map((m) => (
                        <TableRow key={m.id}>
                            <TableCell><Link className="font-medium hover:underline" href={adminUrl(`menus/${m.id}`, adminPath)}>{m.title}</Link></TableCell>
                            <TableCell className="max-md:hidden"><code className="text-xs">{m.handle}</code></TableCell>
                            <TableCell>{m.items_count}</TableCell>
                            <TableCell>
                                {can('sunrice.menus.delete') && (
                                    <Button
                                        variant="ghost" size="sm" className="text-destructive"
                                        onClick={() => window.confirm(`Delete "${m.title}"?`) && router.delete(adminUrl(`menus/${m.id}`, adminPath))}
                                    >Delete</Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                    {menus.length === 0 && <TableRow><TableCell colSpan={4} className="h-24 text-center text-muted-foreground">No menus.</TableCell></TableRow>}
                </TableBody>
            </Table>
        </div>
    );
}
