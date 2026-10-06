import { Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';

interface Row { id: number; handle: string; title: string; fields_count: number }

export default function FieldsetsIndex({ fieldsets }: { fieldsets: Row[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold tracking-tight">Fieldsets</h1>
                {can('sunrice.fieldsets.create') && (
                    <Button asChild><Link href={adminUrl('structure/fieldsets/create', adminPath)}><Plus className="mr-1 h-4 w-4" /> New fieldset</Link></Button>
                )}
            </div>
            <Table>
                <TableHeader><TableRow><TableHead>Title</TableHead><TableHead>Handle</TableHead><TableHead>Fields</TableHead><TableHead className="w-24" /></TableRow></TableHeader>
                <TableBody>
                    {fieldsets.map((f) => (
                        <TableRow key={f.id}>
                            <TableCell><Link className="font-medium hover:underline" href={adminUrl(`structure/fieldsets/${f.id}/edit`, adminPath)}>{f.title}</Link></TableCell>
                            <TableCell><code className="text-xs">{f.handle}</code></TableCell>
                            <TableCell>{f.fields_count}</TableCell>
                            <TableCell>
                                {can('sunrice.fieldsets.delete') && (
                                    <Button
                                        variant="ghost" size="sm" className="text-destructive"
                                        onClick={() => window.confirm(`Delete "${f.title}"?`) && router.delete(adminUrl(`structure/fieldsets/${f.id}`, adminPath))}
                                    >Delete</Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                    {fieldsets.length === 0 && <TableRow><TableCell colSpan={4} className="h-24 text-center text-muted-foreground">No fieldsets.</TableCell></TableRow>}
                </TableBody>
            </Table>
        </div>
    );
}
