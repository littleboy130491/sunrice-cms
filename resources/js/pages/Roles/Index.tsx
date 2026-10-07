import * as React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';

interface Row { id: number; name: string; permissions_count: number; deletable?: boolean }

export default function RolesIndex({ roles }: { roles: Row[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="sunrice-page-title">Roles</h1>
                {can('sunrice.roles.create') && (
                    <Button asChild><Link href={adminUrl('roles/create', adminPath)}><Plus className="mr-1 h-4 w-4" /> New role</Link></Button>
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
                                {(r.deletable ?? can('sunrice.roles.delete')) && (
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
