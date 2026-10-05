import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { SortableContext, arrayMove, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical, Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import FieldList from './FieldList';
import type { ContainerFieldProps } from './registry';
import type { Json } from '@/types';

function SortableRow({ id, children }: { id: string; children: React.ReactNode }) {
    const { attributes, listeners, setNodeRef, transform, transition } = useSortable({ id });
    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className="flex gap-2 rounded-md border p-3"
        >
            <button type="button" className="cursor-grab text-muted-foreground" {...attributes} {...listeners}>
                <GripVertical className="h-4 w-4" />
            </button>
            <div className="flex-1">{children}</div>
        </div>
    );
}

export default function RepeaterField({ field, value, errors, pathPrefix, onChange }: ContainerFieldProps) {
    const rows = (value as Record<string, Json>[]) ?? [];
    const sensors = useSensors(useSensor(PointerSensor), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));

    const update = (index: number, handle: string, v: unknown) => {
        const next = rows.map((row, i) => (i === index ? { ...row, [handle]: v } : row));
        onChange(next);
    };

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over || active.id === over.id) return;
        onChange(arrayMove(rows, Number(active.id), Number(over.id)));
    };

    return (
        <div className="flex flex-col gap-2">
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                <SortableContext items={rows.map((_, i) => String(i))} strategy={verticalListSortingStrategy}>
                    {rows.map((row, i) => (
                        <SortableRow key={i} id={String(i)}>
                            <div className="flex flex-col gap-2">
                                <div className="flex justify-end">
                                    <button type="button" className="text-muted-foreground hover:text-foreground" onClick={() => onChange(rows.filter((_, j) => j !== i))}>
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>
                                <FieldList
                                    fields={field.fields ?? []}
                                    values={row}
                                    errors={errors}
                                    pathPrefix={`${pathPrefix}.${i}`}
                                    onChange={(handle, v) => update(i, handle, v)}
                                />
                            </div>
                        </SortableRow>
                    ))}
                </SortableContext>
            </DndContext>
            <Button type="button" variant="outline" size="sm" onClick={() => onChange([...rows, {}])}>
                <Plus className="mr-1 h-4 w-4" /> Add row
            </Button>
        </div>
    );
}
