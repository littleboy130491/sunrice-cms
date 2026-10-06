import { Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DataTable, type FilterDef } from '@/components/data-table/DataTable';
import { adminUrl } from '@/lib/route';
import type { ColumnDef, Paginated, SharedProps } from '@/types';

interface Props {
    resource: { key: string; label: string; singularLabel: string };
    columns: ColumnDef[];
    rows: Paginated<{ id: string | number; [key: string]: unknown }>;
    meta: { search: string | null; filters: Record<string, string>; sort: string | null };
    can: { create: boolean };
    visibleColumns?: string[];
    filters?: FilterDef[];
}

export default function ResourceIndex({ resource, columns, rows, meta, can, visibleColumns, filters }: Props) {
    const { adminPath } = usePage<SharedProps>().props;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold tracking-tight">{resource.label}</h1>
                {can.create && (
                    <Button asChild>
                        <Link href={adminUrl(`resources/${resource.key}/create`, adminPath)}>
                            <Plus className="mr-1 h-4 w-4" /> New {resource.singularLabel}
                        </Link>
                    </Button>
                )}
            </div>
            <DataTable
                columns={columns}
                rows={rows}
                meta={meta}
                tableKey={`resource.${resource.key}`}
                visibleColumns={visibleColumns}
                filters={filters}
                exportUrl={adminUrl(`resources/${resource.key}/export`, adminPath)}
                bulkUrl={adminUrl(`resources/${resource.key}/bulk`, adminPath)}
                bulkActions={[{ key: 'delete', label: 'Delete', variant: 'destructive', confirm: 'Delete selected records?' }]}
                rowHref={(row) => adminUrl(`resources/${resource.key}/${row.id}/edit`, adminPath)}
            />
        </div>
    );
}
