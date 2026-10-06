import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { SortableContext, arrayMove, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import FieldList from './FieldList';
import ItemControls from './ItemControls';
import type { ContainerFieldProps } from './registry';
import { newItemId, useTranslationMode } from './translation-mode';
import type { Json } from '@/types';

type Row = Record<string, Json> & { _id?: string; _key?: string | null; _hidden?: boolean };

function SortableRow({ id, locked, hidden, children }: { id: string; locked: boolean; hidden: boolean; children: React.ReactNode }) {
    const { attributes, listeners, setNodeRef, transform, transition } = useSortable({ id, disabled: locked });
    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn('flex gap-2 rounded-md border p-3', hidden && 'border-dashed bg-muted/40')}
        >
            {!locked && (
                <button type="button" className="cursor-grab text-muted-foreground" aria-label="Reorder" {...attributes} {...listeners}>
                    <GripVertical className="h-4 w-4" />
                </button>
            )}
            <div className="min-w-0 flex-1">{children}</div>
        </div>
    );
}

export default function RepeaterField({ field, value, errors, pathPrefix, onChange }: ContainerFieldProps) {
    const rows = (value as Row[]) ?? [];
    const { secondary } = useTranslationMode();
    const sensors = useSensors(useSensor(PointerSensor), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));
    const rowId = (row: Row, i: number) => row._id ?? `#${i}`;

    const patch = (index: number, changes: Partial<Row>) => {
        onChange(rows.map((row, i) => (i === index ? { ...row, ...changes } : row)));
    };

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over || active.id === over.id) return;
        const ids = rows.map(rowId);
        onChange(arrayMove(rows, ids.indexOf(String(active.id)), ids.indexOf(String(over.id))));
    };

    return (
        <div className="flex flex-col gap-2">
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                <SortableContext items={rows.map(rowId)} strategy={verticalListSortingStrategy}>
                    {rows.map((row, i) => (
                        <SortableRow key={rowId(row, i)} id={rowId(row, i)} locked={secondary} hidden={!!row._hidden}>
                            <div className="flex flex-col gap-3">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-xs font-medium text-muted-foreground">Row {i + 1}</span>
                                    <ItemControls
                                        id={`${pathPrefix}-${rowId(row, i)}`}
                                        itemKey={row._key}
                                        hidden={!!row._hidden}
                                        locked={secondary}
                                        onKeyChange={(key) => patch(i, { _key: key })}
                                        onHiddenChange={(hidden) => patch(i, { _hidden: hidden })}
                                        onRemove={() => onChange(rows.filter((_, j) => j !== i))}
                                    />
                                </div>
                                <FieldList
                                    fields={field.fields ?? []}
                                    values={row}
                                    errors={errors}
                                    pathPrefix={`${pathPrefix}.${i}`}
                                    onChange={(handle, v) => patch(i, { [handle]: v as Json })}
                                />
                            </div>
                        </SortableRow>
                    ))}
                </SortableContext>
            </DndContext>
            {!secondary && (
                <Button type="button" variant="outline" size="sm" className="self-start" onClick={() => onChange([...rows, { _id: newItemId() }])}>
                    <Plus /> Add row
                </Button>
            )}
        </div>
    );
}
