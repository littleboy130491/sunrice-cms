import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ExternalLink, Plus } from 'lucide-react';
import { DataTable, type FilterDef } from '@/components/data-table/DataTable';
import { Button } from '@/components/ui/button';
import { RelatedMenu, type RelatedLink } from '@/components/app/related-menu';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { AdminTab, ColumnDef, Paginated, SharedProps } from '@/types';

interface Row {
    id: number;
    title: string;
    depth: number;
    slug: string;
    parent: string;
    entries: number;
    languages: string;
    created_at: string | null;
    url: string | null;
}

interface Props {
    related?: RelatedLink[];
    taxonomy: { id: number; handle: string; title: string; hierarchical: boolean; template?: string | null };
    columns: ColumnDef[];
    rows: Paginated<Row>;
    meta: { search: string | null; filters: Record<string, string>; sort: string | null };
    parents: { value: string; label: string }[];
    reorderable: boolean;
    locales: string[];
    mainLocale?: string;
    blueprint: AdminTab[] | null;
}

export default function TermsPage({ taxonomy, columns, rows, meta, parents, reorderable, locales, mainLocale, related }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const main = mainLocale ?? locales[0];
    const canEditTerms = can(`sunrice.terms.${taxonomy.id}.edit`);

    const canDelete = can(`sunrice.terms.${taxonomy.id}.delete`);
    const filters: FilterDef[] = [
        ...(taxonomy.hierarchical && parents.length > 0
            ? [{ key: 'parent', label: 'Parent', type: 'select' as const, options: [{ value: 'root', label: 'Top level only' }, ...parents] }]
            : []),
        ...(locales.length > 1
            ? [{ key: 'missing', label: 'Missing language', type: 'select' as const, options: locales.filter((l) => l !== main).map((l) => ({ value: l, label: `No ${l.toUpperCase()} version` })) }]
            : []),
    ];

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="sunrice-page-title">{taxonomy.title} — terms</h1>
                <div className="flex gap-2">
                    {can(`sunrice.terms.${taxonomy.id}.create`) && (
                        <Button asChild><Link href={adminUrl(`taxonomies/${taxonomy.handle}/terms/create`, adminPath)}><Plus className="mr-1 h-4 w-4" /> New term</Link></Button>
                    )}
                    <RelatedMenu links={related} />
                </div>
            </div>

            {reorderable && <p className="text-sm text-muted-foreground">Drag terms to set their order. A term's parent is set in its editor.</p>}
            <DataTable<Row>
                columns={columns}
                rows={rows}
                meta={meta}
                tableKey={`terms-${taxonomy.handle}`}
                filters={filters}
                searchPlaceholder="Search terms…"
                reorderable={reorderable}
                reorderUrl={adminUrl(`taxonomies/${taxonomy.handle}/terms/reorder`, adminPath)}
                bulkUrl={canDelete ? adminUrl(`taxonomies/${taxonomy.handle}/terms/bulk`, adminPath) : undefined}
                bulkActions={canDelete ? [{ key: 'delete', label: 'Delete', variant: 'destructive', confirm: 'Delete the selected terms? Their child terms are deleted too, and they are removed from every entry.' }] : []}
                rowHref={canEditTerms ? (row) => adminUrl(`taxonomies/${taxonomy.handle}/terms/${row.id}`, adminPath) : undefined}
                renderCell={(row, column) => {
                    if (column.key === 'title') {
                        return (
                            <span className="inline-flex items-center gap-2" style={{ paddingLeft: meta.sort || meta.search ? 0 : row.depth * 20 }}>
                                {row.depth > 0 && !meta.sort && !meta.search && <span className="text-muted-foreground" aria-hidden>└</span>}
                                {row.title}
                                {row.url && (
                                    <a
                                        href={row.url}
                                        target="_blank"
                                        rel="noopener"
                                        onClick={(e) => e.stopPropagation()}
                                        className="text-muted-foreground hover:text-foreground"
                                        aria-label="Visit term page"
                                        title="Visit term page"
                                    >
                                        <ExternalLink className="size-3.5" />
                                    </a>
                                )}
                            </span>
                        );
                    }
                    if (column.key === 'slug') return <code className="text-xs text-muted-foreground">{row.slug}</code>;
                    return undefined;
                }}
            />

        </div>
    );
}
