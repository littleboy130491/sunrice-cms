import * as React from 'react';
import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { SortableContext, arrayMove, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ChevronDown, ChevronUp, GripVertical, Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import FieldList from './FieldList';
import type { ContainerFieldProps } from './registry';
import type { Json } from '@/types';

interface Block {
    id: string;
    type: string;
    values: Record<string, Json>;
}

function SortableBlock({ id, children }: { id: string; children: React.ReactNode }) {
    const { attributes, listeners, setNodeRef, transform, transition } = useSortable({ id });
    return (
        <div ref={setNodeRef} style={{ transform: CSS.Transform.toString(transform), transition }} className="rounded-md border">
            <div className="flex flex-1">
                <button type="button" className="cursor-grab px-2 text-muted-foreground" {...attributes} {...listeners}>
                    <GripVertical className="h-4 w-4" />
                </button>
                <div className="flex-1">{children}</div>
            </div>
        </div>
    );
}

export default function FlexibleField({ field, value, errors, pathPrefix, onChange }: ContainerFieldProps) {
    const blocks = (value as Block[]) ?? [];
    const [collapsed, setCollapsed] = React.useState<Record<string, boolean>>({});
    const sensors = useSensors(useSensor(PointerSensor), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));

    const fieldsets = field.fieldsets ?? [];

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over || active.id === over.id) return;
        const from = blocks.findIndex((b) => b.id === active.id);
        const to = blocks.findIndex((b) => b.id === over.id);
        onChange(arrayMove(blocks, from, to));
    };

    const addBlock = (type: string) => {
        onChange([...blocks, { id: `${Date.now()}${Math.random().toString(36).slice(2, 8)}`, type, values: {} }]);
    };

    return (
        <div className="flex flex-col gap-2">
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                <SortableContext items={blocks.map((b) => b.id)} strategy={verticalListSortingStrategy}>
                    {blocks.map((block, i) => {
                        const fieldset = fieldsets.find((f) => f.handle === block.type);
                        const isCollapsed = !!collapsed[block.id];

                        return (
                            <SortableBlock key={block.id} id={block.id}>
                                <div className="flex items-center justify-between border-b px-3 py-1.5">
                                    <span className="text-sm font-medium">{fieldset?.title ?? block.type}</span>
                                    <span className="flex items-center gap-1">
                                        <button type="button" onClick={() => setCollapsed((c) => ({ ...c, [block.id]: !isCollapsed }))}>
                                            {isCollapsed ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
                                        </button>
                                        <button type="button" onClick={() => onChange(blocks.filter((b) => b.id !== block.id))}>
                                            <X className="h-4 w-4" />
                                        </button>
                                    </span>
                                </div>
                                {!isCollapsed && (
                                    <div className="p-3">
                                        <FieldList
                                            fields={fieldset?.fields ?? []}
                                            values={block.values}
                                            errors={errors}
                                            pathPrefix={`${pathPrefix}.${i}.values`}
                                            onChange={(handle, v) =>
                                                onChange(blocks.map((b) => (b.id === block.id ? { ...b, values: { ...b.values, [handle]: v } } : b)))
                                            }
                                        />
                                    </div>
                                )}
                            </SortableBlock>
                        );
                    })}
                </SortableContext>
            </DndContext>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button type="button" variant="outline" size="sm">
                        <Plus className="mr-1 h-4 w-4" /> Add block
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    {fieldsets.map((f) => (
                        <DropdownMenuItem key={f.handle} onSelect={() => addBlock(f.handle)}>
                            {f.title}
                        </DropdownMenuItem>
                    ))}
                    {fieldsets.length === 0 && <DropdownMenuItem disabled>No fieldsets allowed</DropdownMenuItem>}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
