import * as React from 'react';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { Link, router, usePage } from '@inertiajs/react';
import {
    ColumnDef as TanColumnDef,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import {
    DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent,
} from '@dnd-kit/core';
import {
    SortableContext, arrayMove, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronLeft, ChevronRight, Download, GripVertical, Search, Settings2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTableQuery } from './useTableQuery';
import { adminUrl } from '@/lib/route';
import type { ColumnDef, Paginated, SharedProps } from '@/types';
import { toast } from 'sonner';

export interface FilterDef {
    key: string;
    label: string;
    type: 'select' | 'trashed' | 'status';
    options: { value: string; label: string }[];
}

export interface BulkAction {
    key: string;
    label: string;
    variant?: 'default' | 'destructive';
    confirm?: string;
}

interface Props<T extends { id: number | string }> {
    columns: ColumnDef[];
    rows: Paginated<T>;
    meta: { search: string | null; filters: Record<string, string>; sort: string | null };
    tableKey: string;
    /** The user's saved column choice for this table, if any. */
    visibleColumns?: string[];
    filters?: FilterDef[];
    bulkActions?: BulkAction[];
    bulkUrl?: string;
    exportUrl?: string;
    reorderable?: boolean;
    reorderUrl?: string;
    searchPlaceholder?: string;
    /** Where a row links to; null for rows that can't be opened. */
    rowHref?: (row: T) => string | null;
    renderCell?: (row: T, column: ColumnDef) => React.ReactNode;
}

function PageButton({ url, label, children }: { url: string | null; label: string; children: React.ReactNode }) {
    return url ? (
        <Button variant="outline" size="icon" className="size-8" asChild>
            <Link href={url} preserveScroll aria-label={label}>
                {children}
            </Link>
        </Button>
    ) : (
        <Button variant="outline" size="icon" className="size-8" disabled aria-label={label}>
            {children}
        </Button>
    );
}

function SortableRow({ id, children }: { id: number | string; children: React.ReactNode }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });
    const style = { transform: CSS.Transform.toString(transform), transition, opacity: isDragging ? 0.5 : 1 };

    return (
        <TableRow ref={setNodeRef} style={style}>
            <TableCell className="w-8 cursor-grab" {...attributes} {...listeners}>
                <GripVertical className="size-4 text-muted-foreground" />
            </TableCell>
            {children}
        </TableRow>
    );
}

export function DataTable<T extends { id: number | string }>({
    columns: allColumns,
    rows,
    meta,
    tableKey,
    visibleColumns: savedColumns,
    filters = [],
    bulkActions = [],
    bulkUrl,
    exportUrl,
    reorderable = false,
    reorderUrl,
    searchPlaceholder = 'Search…',
    rowHref,
    renderCell,
}: Props<T>) {
    const { adminPath } = usePage<SharedProps>().props;
    const { state, update } = useTableQuery({
        search: meta.search ?? '',
        filters: meta.filters ?? {},
        sort: meta.sort ?? '',
    });

    const [visible, setVisible] = React.useState<string[]>(() =>
        savedColumns && savedColumns.length > 0 ? savedColumns : allColumns.map((c) => c.key),
    );
    const [selection, setSelection] = React.useState<Record<string, boolean>>({});
    const [orderedData, setOrderedData] = React.useState<T[]>(rows.data);

    React.useEffect(() => setOrderedData(rows.data), [rows.data]);
    React.useEffect(() => setSelection({}), [rows.data, rows.current_page]);

    const visibleColumns = React.useMemo(
        () => allColumns.filter((c) => visible.includes(c.key)),
        [allColumns, visible],
    );

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const saveColumns = (keys: string[]) => {
        setVisible(keys);
        // A JSON endpoint, so not an Inertia visit (that would show an error modal).
        const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
        fetch(adminUrl(`table-preferences/${tableKey}`, adminPath), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf },
            body: JSON.stringify({ columns: keys }),
        })
            .then((res) => { if (!res.ok) throw new Error(); })
            .catch(() => toast.error('Could not save your column choice.'));
    };

    const toggleSort = (key: string) => {
        const next = state.sort === key ? `-${key}` : state.sort === `-${key}` ? '' : key;
        update({ sort: next });
    };

    const selectedIds = Object.keys(selection).filter((k) => selection[k]);

    const runBulk = (action: BulkAction) => {
        if (!bulkUrl || selectedIds.length === 0) return;
        if (action.confirm && !window.confirm(action.confirm)) return;
        router.post(bulkUrl, { action: action.key, ids: selectedIds }, {
            preserveScroll: true,
            onSuccess: () => setSelection({}),
        });
    };

    const onDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;
        const oldIndex = orderedData.findIndex((r) => r.id === active.id);
        const newIndex = orderedData.findIndex((r) => r.id === over.id);
        const next = arrayMove(orderedData, oldIndex, newIndex);
        setOrderedData(next);
        if (reorderUrl) {
            router.post(reorderUrl, { items: next.map((r) => r.id) }, { preserveScroll: true });
        }
    };

    const exportHref = () => {
        if (!exportUrl) return null;
        const params = new URLSearchParams();
        if (state.search) params.set('search', state.search);
        if (state.sort) params.set('sort', state.sort);
        Object.entries(state.filters).forEach(([k, v]) => params.set(`filters[${k}]`, v));
        const qs = params.toString();
        return `${exportUrl}${qs ? `?${qs}` : ''}`;
    };

    const cellValue = (row: T, column: ColumnDef): React.ReactNode => {
        if (renderCell) {
            const custom = renderCell(row, column);
            if (custom !== undefined) return custom;
        }
        const value = (row as Record<string, unknown>)[column.key];
        if (value === null || value === undefined) return '—';
        if (column.type === 'boolean') return value ? 'Yes' : 'No';
        if (column.type === 'badge') return <Badge variant="secondary">{String(value)}</Badge>;
        if (typeof value === 'object') return <code className="text-xs">{JSON.stringify(value)}</code>;
        return String(value);
    };

    const allSelected = orderedData.length > 0 && orderedData.every((r) => selection[String(r.id)]);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center gap-2">
                <div className="relative w-full max-w-xs">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        className="pl-9"
                        placeholder={searchPlaceholder}
                        aria-label="Search"
                        value={state.search}
                        onChange={(e) => update({ search: e.target.value }, true)}
                    />
                </div>
                {filters.map((f) => (
                    <Select
                        key={f.key}
                        value={state.filters[f.key] ?? ''}
                        onValueChange={(v) => update({ filters: { ...state.filters, [f.key]: v === '__all' ? '' : v } })}
                    >
                        <SelectTrigger className="w-36">
                            <SelectValue placeholder={f.label} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all">All</SelectItem>
                            {f.options.map((o) => (
                                <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                ))}
                <div className="ml-auto flex items-center gap-2">
                    {selectedIds.length > 0 && bulkActions.length > 0 && (
                        <div className="flex items-center gap-2">
                            <span className="text-sm text-muted-foreground">{selectedIds.length} selected</span>
                            {bulkActions.map((a) => (
                                <Button key={a.key} variant={a.variant ?? 'outline'} onClick={() => runBulk(a)}>
                                    {a.label}
                                </Button>
                            ))}
                        </div>
                    )}
                    {exportUrl && (
                        <Button variant="outline" asChild>
                            <a href={exportHref() ?? '#'}>
                                <Download /> Export
                            </a>
                        </Button>
                    )}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline">
                                <Settings2 /> Columns
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {allColumns.map((c) => (
                                <DropdownMenuCheckboxItem
                                    key={c.key}
                                    checked={visible.includes(c.key)}
                                    onCheckedChange={(checked) =>
                                        saveColumns(
                                            checked
                                                ? [...visible, c.key]
                                                : visible.filter((k) => k !== c.key),
                                        )
                                    }
                                >
                                    {c.label}
                                </DropdownMenuCheckboxItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <CollapsibleCard title="Results" storageKey={`table:${tableKey}`} contentClassName="p-0">
                <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                    <div className="overflow-hidden">
                    <Table>
                        <TableHeader className="bg-muted/60">
                            <TableRow className="hover:bg-transparent">
                                {reorderable && <TableHead className="w-8" />}
                                <TableHead className="w-8">
                                    <Checkbox
                                        aria-label="Select all rows"
                                        checked={allSelected}
                                        onCheckedChange={(checked) =>
                                            setSelection(
                                                checked
                                                    ? Object.fromEntries(orderedData.map((r) => [String(r.id), true]))
                                                    : {},
                                            )
                                        }
                                    />
                                </TableHead>
                                {visibleColumns.map((c) => (
                                    <TableHead key={c.key}>
                                        {c.sortable ? (
                                            <button
                                                type="button"
                                                className="-ml-2 inline-flex h-8 items-center gap-1 rounded-md px-2 hover:bg-accent hover:text-accent-foreground"
                                                onClick={() => toggleSort(c.key)}
                                            >
                                                {c.label}
                                                {state.sort === c.key ? (
                                                    <ArrowUp className="h-3 w-3" />
                                                ) : state.sort === `-${c.key}` ? (
                                                    <ArrowDown className="h-3 w-3" />
                                                ) : (
                                                    <ArrowUpDown className="h-3 w-3 opacity-40" />
                                                )}
                                            </button>
                                        ) : (
                                            c.label
                                        )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <SortableContext items={orderedData.map((r) => r.id)} strategy={verticalListSortingStrategy}>
                                {orderedData.map((row) => {
                                    const cells = (
                                        <>
                                            <TableCell>
                                                <Checkbox
                                                    aria-label="Select row"
                                                    checked={!!selection[String(row.id)]}
                                                    onCheckedChange={(checked) =>
                                                        setSelection((s) => ({ ...s, [String(row.id)]: !!checked }))
                                                    }
                                                />
                                            </TableCell>
                                            {visibleColumns.map((c) => (
                                                <TableCell key={c.key}>
                                                    {rowHref?.(row) ? (
                                                        <Link href={rowHref(row) as string} className="font-medium underline-offset-4 hover:underline">
                                                            {cellValue(row, c)}
                                                        </Link>
                                                    ) : (
                                                        cellValue(row, c)
                                                    )}
                                                </TableCell>
                                            ))}
                                        </>
                                    );

                                    return reorderable ? (
                                        <SortableRow key={row.id} id={row.id}>
                                            {cells}
                                        </SortableRow>
                                    ) : (
                                        <TableRow key={row.id}>{cells}</TableRow>
                                    );
                                })}
                                {orderedData.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={visibleColumns.length + (reorderable ? 2 : 1)} className="h-24 text-center text-muted-foreground">
                                            No results.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </SortableContext>
                        </TableBody>
                    </Table>
                    </div>
                </DndContext>

                <div className="flex items-center justify-between gap-4 px-6 py-4 text-sm text-muted-foreground">
                    <span>
                        {rows.total === 0 ? 'No rows' : `Showing ${rows.from ?? 0}–${rows.to ?? 0} of ${rows.total}`}
                    </span>
                    {rows.last_page > 1 && (
                        <div className="flex items-center gap-2">
                            <span className="hidden sm:inline">
                                Page {rows.current_page} of {rows.last_page}
                            </span>
                            <PageButton url={rows.links[0]?.url ?? null} label="Previous page">
                                <ChevronLeft />
                            </PageButton>
                            <PageButton url={rows.links[rows.links.length - 1]?.url ?? null} label="Next page">
                                <ChevronRight />
                            </PageButton>
                        </div>
                    )}
                </div>
            </CollapsibleCard>
        </div>
    );
}
