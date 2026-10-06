import * as React from 'react';
import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { SortableContext, arrayMove, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ChevronDown, ChevronUp, GripVertical, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import FieldList from './FieldList';
import ItemControls from './ItemControls';
import type { ContainerFieldProps } from './registry';
import { newItemId, useTranslationMode } from './translation-mode';
import type { Json } from '@/types';

interface Block {
    id: string;
    type: string;
    key?: string | null;
    hidden?: boolean;
    values: Record<string, Json>;
}

function SortableBlock({ id, locked, hidden, children }: { id: string; locked: boolean; hidden: boolean; children: React.ReactNode }) {
    const { attributes, listeners, setNodeRef, transform, transition } = useSortable({ id, disabled: locked });
    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn('rounded-md border', hidden && 'border-dashed bg-muted/40')}
        >
            <div className="flex flex-1">
                {!locked && (
                    <button type="button" className="cursor-grab px-2 text-muted-foreground" aria-label="Reorder" {...attributes} {...listeners}>
                        <GripVertical className="h-4 w-4" />
                    </button>
                )}
                <div className="min-w-0 flex-1">{children}</div>
            </div>
        </div>
    );
}

export default function FlexibleField({ field, value, errors, pathPrefix, onChange }: ContainerFieldProps) {
    const blocks = (value as Block[]) ?? [];
    const { secondary } = useTranslationMode();
    const [collapsed, setCollapsed] = React.useState<Record<string, boolean>>({});
    const sensors = useSensors(useSensor(PointerSensor), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));

    const fieldsets = field.fieldsets ?? [];

    const patch = (id: string, changes: Partial<Block>) => onChange(blocks.map((b) => (b.id === id ? { ...b, ...changes } : b)));

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over || active.id === over.id) return;
        const from = blocks.findIndex((b) => b.id === active.id);
        const to = blocks.findIndex((b) => b.id === over.id);
        onChange(arrayMove(blocks, from, to));
    };

    const addBlock = (type: string) => {
        onChange([...blocks, { id: newItemId(), type, key: null, hidden: false, values: {} }]);
    };

    return (
        <div className="flex flex-col gap-2">
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                <SortableContext items={blocks.map((b) => b.id)} strategy={verticalListSortingStrategy}>
                    {blocks.map((block, i) => {
                        const fieldset = fieldsets.find((f) => f.handle === block.type);
                        const isCollapsed = !!collapsed[block.id];

                        return (
                            <SortableBlock key={block.id} id={block.id} locked={secondary} hidden={!!block.hidden}>
                                <div className="flex flex-wrap items-center justify-between gap-2 border-b px-3 py-1.5">
                                    <button
                                        type="button"
                                        className="flex items-center gap-1.5 text-sm font-medium"
                                        onClick={() => setCollapsed((c) => ({ ...c, [block.id]: !isCollapsed }))}
                                    >
                                        {isCollapsed ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
                                        {fieldset?.title ?? block.type}
                                    </button>
                                    <ItemControls
                                        id={block.id}
                                        itemKey={block.key}
                                        hidden={!!block.hidden}
                                        locked={secondary}
                                        onKeyChange={(key) => patch(block.id, { key })}
                                        onHiddenChange={(hidden) => patch(block.id, { hidden })}
                                        onRemove={() => onChange(blocks.filter((b) => b.id !== block.id))}
                                    />
                                </div>
                                {!isCollapsed && (
                                    <div className="p-3">
                                        <FieldList
                                            fields={fieldset?.fields ?? []}
                                            values={block.values}
                                            errors={errors}
                                            pathPrefix={`${pathPrefix}.${i}.values`}
                                            onChange={(handle, v) => patch(block.id, { values: { ...block.values, [handle]: v as Json } })}
                                        />
                                    </div>
                                )}
                            </SortableBlock>
                        );
                    })}
                </SortableContext>
            </DndContext>
            {!secondary && (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button type="button" variant="outline" size="sm" className="self-start">
                            <Plus /> Add block
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        {fieldsets.map((f) => (
                            <DropdownMenuItem key={f.handle} onSelect={() => addBlock(f.handle)}>
                                {f.title}
                            </DropdownMenuItem>
                        ))}
                        {fieldsets.length === 0 && <DropdownMenuItem disabled>No fieldsets allowed</DropdownMenuItem>}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
        </div>
    );
}
