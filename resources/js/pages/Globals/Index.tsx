import { Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';

interface Row { id: number; handle: string; title: string; group: string; translatable: boolean; blueprint: { id: number; title: string } | null }

export default function GlobalsIndex({ globals }: { globals: Row[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Globals & template parts</h1>
                {can('sunrice.manage-globals') && (
                    <Button asChild><Link href={adminUrl('globals/create', adminPath)}><Plus className="mr-1 h-4 w-4" /> New global</Link></Button>
                )}
            </div>
            <Table>
                <TableHeader><TableRow><TableHead>Title</TableHead><TableHead>Handle</TableHead><TableHead>Group</TableHead><TableHead>Blueprint</TableHead><TableHead className="w-24" /></TableRow></TableHeader>
                <TableBody>
                    {globals.map((g) => (
                        <TableRow key={g.id}>
                            <TableCell><Link className="font-medium hover:underline" href={adminUrl(`globals/${g.id}/edit`, adminPath)}>{g.title}</Link></TableCell>
                            <TableCell><code className="text-xs">{g.handle}</code></TableCell>
                            <TableCell><Badge variant="secondary">{g.group}</Badge></TableCell>
                            <TableCell>{g.blueprint?.title ?? '—'}</TableCell>
                            <TableCell>
                                {can('sunrice.manage-globals') && (
                                    <Button
                                        variant="ghost" size="sm" className="text-destructive"
                                        onClick={() => window.confirm(`Delete "${g.title}"?`) && router.delete(adminUrl(`globals/${g.id}`, adminPath))}
                                    >Delete</Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                    {globals.length === 0 && <TableRow><TableCell colSpan={5} className="h-24 text-center text-muted-foreground">No globals.</TableCell></TableRow>}
                </TableBody>
            </Table>
        </div>
    );
}
