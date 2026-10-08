import { Link, router, usePage } from '@inertiajs/react';
import * as React from 'react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';

interface Row { id: number; handle: string; title: string; items_count: number }

export default function MenusIndex({ menus }: { menus: Row[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="sunrice-page-title">Menus</h1>
                {can('sunrice.menus.create') && (
                    <Button asChild><Link href={adminUrl('menus/create', adminPath)}><Plus className="mr-1 h-4 w-4" /> New menu</Link></Button>
                )}
            </div>
            <Table>
                <TableHeader><TableRow><TableHead>Title</TableHead><TableHead className="max-md:hidden">Handle</TableHead><TableHead>Items</TableHead><TableHead className="w-24" /></TableRow></TableHeader>
                <TableBody>
                    {menus.map((m) => (
                        <TableRow key={m.id}>
                            <TableCell><Link className="font-medium hover:underline" href={adminUrl(`menus/${m.id}/edit`, adminPath)}>{m.title}</Link></TableCell>
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
