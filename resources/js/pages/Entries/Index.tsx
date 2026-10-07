import { Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable, type FilterDef } from '@/components/data-table/DataTable';
import { adminUrl } from '@/lib/route';
import type { ColumnDef, Paginated, SharedProps } from '@/types';

interface Row { id: number; title: string; status: string; author: string; created_at: string | null; updated_at: string | null; [field: `field.${string}`]: string | null }

interface Props {
    collection: { id: number; handle: string; title: string };
    columns: ColumnDef[];
    rows: Paginated<Row>;
    meta: { search: string | null; filters: Record<string, string>; sort: string | null };
    can: { create: boolean; listing?: boolean; reorder?: boolean };
    /** Ordered by hand, but a search, filter or column sort is hiding that order. */
    reorderPaused?: boolean;
    visibleColumns?: string[];
    /** Where this collection's column choice is saved. */
    columnsKey?: string;
    /** People who created entries here, for the Created by filter. */
    authors?: { value: string; label: string }[];
}

export default function EntriesIndex({ collection, columns, rows, meta, can, visibleColumns, columnsKey = 'entries', reorderPaused, authors = [] }: Props) {
    const { adminPath } = usePage<SharedProps>().props;

    const filters: FilterDef[] = [
        {
            key: 'status', label: 'Status', type: 'status',
            options: [
                { value: 'published', label: 'Published' },
                { value: 'draft', label: 'Draft' },
                { value: 'scheduled', label: 'Scheduled' },
            ],
        },
        ...(authors.length > 0
            ? [{ key: 'author', label: 'Created by', type: 'select' as const, options: [...authors, { value: 'none', label: 'No author' }] }]
            : []),
        {
            key: 'trashed', label: 'Trash', type: 'trashed',
            options: [{ value: 'only', label: 'Trashed' }],
        },
    ];

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="sunrice-page-title">{collection.title}</h1>
                <div className="flex gap-2">
                    {can.listing && (
                        <Button variant="outline" asChild>
                            <Link href={adminUrl(`collections/${collection.handle}/listing`, adminPath)}>Listing page</Link>
                        </Button>
                    )}
                    {can.create && (
                        <Button asChild>
                            <Link href={adminUrl(`collections/${collection.handle}/entries/create`, adminPath)}>
                                <Plus className="mr-1 h-4 w-4" /> New entry
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
            {reorderPaused && (
                <p className="text-sm text-muted-foreground">
                    This collection is ordered by hand. Clear the search, filters and column sorting to drag entries into order.
                </p>
            )}
            {can.reorder && <p className="text-sm text-muted-foreground">Drag entries to set the order they appear in on the site.</p>}
            <DataTable<Row>
                columns={columns}
                rows={rows}
                meta={meta}
                tableKey={columnsKey}
                reorderable={!!can.reorder}
                reorderUrl={adminUrl(`collections/${collection.handle}/entries/reorder`, adminPath)}
                visibleColumns={visibleColumns}
                filters={filters}
                exportUrl={adminUrl(`collections/${collection.handle}/entries/export`, adminPath)}
                bulkUrl={adminUrl(`collections/${collection.handle}/entries/bulk`, adminPath)}
                bulkActions={[
                    { key: 'trash', label: 'Trash', confirm: 'Move selected entries to trash?' },
                    { key: 'restore', label: 'Restore' },
                    { key: 'publish', label: 'Publish' },
                    { key: 'delete', label: 'Delete forever', variant: 'destructive', confirm: 'Permanently delete? This cannot be undone.' },
                ]}
                // Trashed entries can't be opened: restore them first (bulk action).
                rowHref={(row) => (row.status === 'trashed' ? null : adminUrl(`entries/${row.id}`, adminPath))}
                renderCell={(row, column) => {
                    if (column.key === 'status') {
                        return <Badge className="capitalize" variant={row.status === 'published' ? 'success' : row.status === 'trashed' ? 'destructive' : 'secondary'}>{row.status}</Badge>;
                    }
                    return undefined;
                }}
            />
        </div>
    );
}
