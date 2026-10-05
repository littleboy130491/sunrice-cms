import * as React from 'react';
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
import { ArrowDown, ArrowUp, ArrowUpDown, Download, GripVertical, Search, Settings2 } from 'lucide-react';
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
    filters?: FilterDef[];
    bulkActions?: BulkAction[];
    bulkUrl?: string;
    exportUrl?: string;
    reorderable?: boolean;
    reorderUrl?: string;
    searchPlaceholder?: string;
    rowHref?: (row: T) => string;
    renderCell?: (row: T, column: ColumnDef) => React.ReactNode;
}

function SortableRow({ id, children }: { id: number | string; children: React.ReactNode }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });
    const style = { transform: CSS.Transform.toString(transform), transition, opacity: isDragging ? 0.5 : 1 };

    return (
        <TableRow ref={setNodeRef} style={style}>
            <TableCell className="w-8 cursor-grab" {...attributes} {...listeners}>
                <GripVertical className="h-4 w-4 text-muted-foreground" />
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
        (rows as { visibleColumns?: string[] }).visibleColumns ?? allColumns.map((c) => c.key),
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
        router.put(adminUrl(`table-preferences/${tableKey}`, adminPath), { columns: keys }, { preserveState: true });
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
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-2">
                <div className="relative max-w-xs flex-1">
                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        className="pl-8"
                        placeholder={searchPlaceholder}
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
                                <Button key={a.key} size="sm" variant={a.variant ?? 'outline'} onClick={() => runBulk(a)}>
                                    {a.label}
                                </Button>
                            ))}
                        </div>
                    )}
                    {exportUrl && (
                        <Button variant="outline" size="sm" asChild>
                            <a href={exportHref() ?? '#'}>
                                <Download className="mr-1 h-4 w-4" /> Export
                            </a>
                        </Button>
                    )}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm">
                                <Settings2 className="mr-1 h-4 w-4" /> Columns
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

            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                <Table>
                    <TableHeader>
                        <TableRow>
                            {reorderable && <TableHead className="w-8" />}
                            <TableHead className="w-8">
                                <Checkbox
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
                                        <button className="inline-flex items-center gap-1" onClick={() => toggleSort(c.key)}>
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
                                                checked={!!selection[String(row.id)]}
                                                onCheckedChange={(checked) =>
                                                    setSelection((s) => ({ ...s, [String(row.id)]: !!checked }))
                                                }
                                            />
                                        </TableCell>
                                        {visibleColumns.map((c) => (
                                            <TableCell key={c.key}>
                                                {rowHref ? (
                                                    <Link href={rowHref(row)} className="hover:underline">
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
                                    <TableCell colSpan={visibleColumns.length + 2} className="h-24 text-center text-muted-foreground">
                                        No results.
                                    </TableCell>
                                </TableRow>
                            )}
                        </SortableContext>
                    </TableBody>
                </Table>
            </DndContext>

            <div className="flex items-center justify-between text-sm text-muted-foreground">
                <span>
                    {rows.from ?? 0}–{rows.to ?? 0} of {rows.total}
                </span>
                <div className="flex gap-1">
                    {rows.links.map((link, i) => (
                        <Button
                            key={i}
                            size="sm"
                            variant={link.active ? 'default' : 'outline'}
                            disabled={!link.url}
                            asChild={!!link.url}
                        >
                            {link.url ? (
                                <Link href={link.url} preserveScroll dangerouslySetInnerHTML={{ __html: link.label }} />
                            ) : (
                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                            )}
                        </Button>
                    ))}
                </div>
            </div>
        </div>
    );
}
