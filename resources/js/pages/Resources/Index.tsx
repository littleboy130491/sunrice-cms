import { Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table/DataTable';
import { adminUrl } from '@/lib/route';
import type { ColumnDef, Paginated, SharedProps } from '@/types';

interface Props {
    resource: { key: string; label: string; singularLabel: string };
    columns: ColumnDef[];
    rows: Paginated<{ id: string | number; [key: string]: unknown }>;
    meta: { search: string | null; filters: Record<string, string>; sort: string | null };
    can: { create: boolean };
}

export default function ResourceIndex({ resource, columns, rows, meta, can }: Props) {
    const { adminPath } = usePage<SharedProps>().props;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-semibold">{resource.label}</h1>
                {can.create && (
                    <Button asChild>
                        <Link href={adminUrl(adminPath, `resources/${resource.key}/create`)}>
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
                exportUrl={adminUrl(adminPath, `resources/${resource.key}/export`)}
                bulkUrl={adminUrl(adminPath, `resources/${resource.key}/bulk`)}
                bulkActions={[{ key: 'delete', label: 'Delete', variant: 'destructive', confirm: 'Delete selected records?' }]}
                rowHref={(row) => adminUrl(adminPath, `resources/${resource.key}/${row.id}/edit`)}
            />
        </div>
    );
}
