import { Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';

interface Row { id: number; handle: string; title: string; fields_count: number; used_by: { type: string; title: string }[] }

export default function BlueprintsIndex({ blueprints }: { blueprints: Row[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold tracking-tight">Blueprints</h1>
                {can('sunrice.blueprints.create') && (
                    <Button asChild><Link href={adminUrl('structure/blueprints/create', adminPath)}><Plus className="mr-1 h-4 w-4" /> New blueprint</Link></Button>
                )}
            </div>
            <Table>
                <TableHeader><TableRow><TableHead>Title</TableHead><TableHead className="max-md:hidden">Handle</TableHead><TableHead className="max-md:hidden">Fields</TableHead><TableHead>Used by</TableHead><TableHead className="w-24" /></TableRow></TableHeader>
                <TableBody>
                    {blueprints.map((b) => (
                        <TableRow key={b.id}>
                            <TableCell>{can('sunrice.blueprints.edit') ? (
                                    <Link className="font-medium hover:underline" href={adminUrl(`structure/blueprints/${b.id}/edit`, adminPath)}>{b.title}</Link>
                                ) : <span className="font-medium">{b.title}</span>}</TableCell>
                            <TableCell className="max-md:hidden"><code className="text-xs">{b.handle}</code></TableCell>
                            <TableCell className="max-md:hidden">{b.fields_count}</TableCell>
                            <TableCell>
                                {b.used_by.length === 0 ? (
                                    <span className="text-muted-foreground">Not used</span>
                                ) : (
                                    <div className="flex flex-wrap gap-1">
                                        {b.used_by.map((u) => (
                                            <span key={`${u.type}-${u.title}`} className="rounded-md border px-1.5 py-0.5 text-xs">
                                                <span className="text-muted-foreground">{u.type}:</span> {u.title}
                                            </span>
                                        ))}
                                    </div>
                                )}
                            </TableCell>
                            <TableCell>
                                {can('sunrice.blueprints.delete') && (
                                    <Button
                                        variant="ghost" size="sm" className="text-destructive"
                                        onClick={() => window.confirm(`Delete "${b.title}"?`) && router.delete(adminUrl(`structure/blueprints/${b.id}`, adminPath))}
                                    >Delete</Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                    {blueprints.length === 0 && <TableRow><TableCell colSpan={5} className="h-24 text-center text-muted-foreground">No blueprints.</TableCell></TableRow>}
                </TableBody>
            </Table>
        </div>
    );
}
