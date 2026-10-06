import { Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';

interface CollectionRow {
    id: number;
    handle: string;
    title: string;
    blueprint: { id: number; title: string; handle: string } | null;
    taxonomies: { id: number; title: string }[];
    entries_count: number;
}

export default function CollectionsIndex({ collections }: { collections: CollectionRow[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const canCreate = can('sunrice.collections.create');
    const canDelete = can('sunrice.collections.delete');

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold tracking-tight">Collections</h1>
                {canCreate && (
                    <Button asChild>
                        <Link href={adminUrl('structure/collections/create', adminPath)}>
                            <Plus className="mr-1 h-4 w-4" /> New collection
                        </Link>
                    </Button>
                )}
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Title</TableHead>
                        <TableHead className="max-md:hidden">Handle</TableHead>
                        <TableHead className="max-md:hidden">Blueprint</TableHead>
                        <TableHead className="max-md:hidden">Taxonomies</TableHead>
                        <TableHead>Entries</TableHead>
                        <TableHead className="w-24" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {collections.map((c) => (
                        <TableRow key={c.id}>
                            <TableCell>
                                {can('sunrice.collections.edit') ? (
                                    <Link className="font-medium hover:underline" href={adminUrl(`structure/collections/${c.id}/edit`, adminPath)}>{c.title}</Link>
                                ) : <span className="font-medium">{c.title}</span>}
                            </TableCell>
                            <TableCell className="max-md:hidden"><code className="text-xs">{c.handle}</code></TableCell>
                            <TableCell className="max-md:hidden">{c.blueprint?.title ?? '—'}</TableCell>
                            <TableCell className="max-md:hidden">
                                <div className="flex flex-wrap gap-1">
                                    {c.taxonomies.map((t) => <Badge key={t.id} variant="secondary">{t.title}</Badge>)}
                                </div>
                            </TableCell>
                            <TableCell>
                                <Link href={adminUrl(`collections/${c.handle}/entries`, adminPath)} className="hover:underline">
                                    {c.entries_count}
                                </Link>
                            </TableCell>
                            <TableCell>
                                {canDelete && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive"
                                        onClick={() => {
                                            if (window.confirm(`Delete "${c.title}" and all its entries?`)) {
                                                router.delete(adminUrl(`structure/collections/${c.id}`, adminPath));
                                            }
                                        }}
                                    >
                                        Delete
                                    </Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                    {collections.length === 0 && (
                        <TableRow><TableCell colSpan={6} className="h-24 text-center text-muted-foreground">No collections.</TableCell></TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}
