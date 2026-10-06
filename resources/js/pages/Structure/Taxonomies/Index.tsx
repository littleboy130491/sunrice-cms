import { Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';

interface Row { id: number; handle: string; title: string; hierarchical: boolean; terms_count: number }

export default function TaxonomiesIndex({ taxonomies }: { taxonomies: Row[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold tracking-tight">Taxonomies</h1>
                {can('sunrice.taxonomies.create') && (
                    <Button asChild><Link href={adminUrl('structure/taxonomies/create', adminPath)}><Plus className="mr-1 h-4 w-4" /> New taxonomy</Link></Button>
                )}
            </div>
            <Table>
                <TableHeader><TableRow><TableHead>Title</TableHead><TableHead className="max-md:hidden">Handle</TableHead><TableHead className="max-md:hidden">Hierarchy</TableHead><TableHead>Terms</TableHead><TableHead className="w-24" /></TableRow></TableHeader>
                <TableBody>
                    {taxonomies.map((t) => (
                        <TableRow key={t.id}>
                            <TableCell><Link className="font-medium hover:underline" href={adminUrl(`structure/taxonomies/${t.id}/edit`, adminPath)}>{t.title}</Link></TableCell>
                            <TableCell className="max-md:hidden"><code className="text-xs">{t.handle}</code></TableCell>
                            <TableCell className="max-md:hidden">{t.hierarchical && <Badge variant="secondary">hierarchical</Badge>}</TableCell>
                            <TableCell><Link href={adminUrl(`taxonomies/${t.handle}`, adminPath)} className="hover:underline">{t.terms_count}</Link></TableCell>
                            <TableCell>
                                {can('sunrice.taxonomies.delete') && (
                                    <Button
                                        variant="ghost" size="sm" className="text-destructive"
                                        onClick={() => window.confirm(`Delete "${t.title}" and all its terms?`) && router.delete(adminUrl(`structure/taxonomies/${t.id}`, adminPath))}
                                    >Delete</Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                    {taxonomies.length === 0 && <TableRow><TableCell colSpan={5} className="h-24 text-center text-muted-foreground">No taxonomies.</TableCell></TableRow>}
                </TableBody>
            </Table>
        </div>
    );
}
