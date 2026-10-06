import * as React from 'react';
import { ChevronDown, ChevronRight, GripVertical, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Checkbox } from '@/components/ui/checkbox';
import { Switch } from '@/components/ui/switch';
import {
    DndContext, KeyboardSensor, PointerSensor, closestCenter, useSensor, useSensors, type DragEndEvent,
} from '@dnd-kit/core';
import { SortableContext, arrayMove, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import type { Json } from '@/types';

export interface BuilderField {
    handle: string;
    type: string;
    label?: string;
    required?: boolean;
    /** Unset = the type's default (text types on, others off). */
    translatable?: boolean;
    instructions?: string;
    conditions?: { field?: string; operator?: string; value?: string | number | boolean } | null;
    config?: { [key: string]: Json };
    fields?: BuilderField[];      // legacy location of group / repeater children (now config.fields)
    fieldset?: string;            // fieldset include
}

export interface FieldTypeDef {
    type: string;
    /** Whether fields of this type are translatable unless set otherwise. */
    translatable?: boolean;
    settings: { key?: string; handle?: string; label: string; type: string; options?: unknown }[];
}

interface Props {
    value: BuilderField[];
    onChange: (fields: BuilderField[]) => void;
    fieldTypes: FieldTypeDef[];
    fieldsets: { id: number; title: string; handle: string }[];
    depth?: number;
    /** Show the per-field Translatable switch (blueprints and fieldsets). */
    translatable?: boolean;
}

const CONTAINER_TYPES = ['group', 'repeater'];

function settingKey(def: FieldTypeDef['settings'][number]): string {
    return def.key ?? def.handle ?? '';
}

function childFields(field: BuilderField): BuilderField[] {
    return (field.config?.fields as unknown as BuilderField[] | undefined) ?? field.fields ?? [];
}

function slugify(input: string): string {
    return input.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
}

/** The fieldset a `fieldset` field includes (config.fieldset; older data kept it at the top level). */
function includedFieldset(field: BuilderField): string {
    return String(field.config?.fieldset ?? field.fieldset ?? '');
}

function SettingControl({
    def, config, onChange, fieldsets,
}: {
    def: FieldTypeDef['settings'][number];
    config: Record<string, unknown>;
    onChange: (v: unknown) => void;
    fieldsets: Props['fieldsets'];
}) {
    const key = settingKey(def);

    switch (def.type) {
        case 'boolean':
        case 'toggle':
            return (
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox checked={!!config[key]} onCheckedChange={onChange} />
                    {def.label}
                </label>
            );
        case 'number':
            return (
                <Input type="number" value={(config[key] as number) ?? ''} onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))} />
            );
        case 'select':
            return (
                <Select value={String(config[key] ?? '')} onValueChange={onChange}>
                    <SelectTrigger><SelectValue placeholder="Choose…" /></SelectTrigger>
                    <SelectContent>
                        {((def.options as { value: string; label: string }[]) ?? []).map((o) => (
                            <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );
        case 'multiselect': {
            const selected = (config[key] as string[]) ?? [];
            const options = (def.options as { value: string; label: string }[]) ?? [];
            return (
                <div className="flex flex-col gap-1">
                    {options.map((o) => (
                        <label key={o.value} className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={selected.includes(o.value)}
                                onCheckedChange={(checked) => onChange(checked ? [...selected, o.value] : selected.filter((v) => v !== o.value))}
                            />
                            {o.label}
                        </label>
                    ))}
                    {options.length === 0 && <span className="text-xs text-muted-foreground">Nothing to choose from yet.</span>}
                </div>
            );
        }
        case 'fieldset':
            return (
                <Select value={String(config[key] ?? '')} onValueChange={onChange}>
                    <SelectTrigger><SelectValue placeholder="Choose a fieldset…" /></SelectTrigger>
                    <SelectContent>
                        {fieldsets.map((f) => <SelectItem key={f.id} value={f.handle}>{f.title}</SelectItem>)}
                    </SelectContent>
                </Select>
            );
        case 'handles':
            return (
                <div className="flex flex-col gap-1">
                    {fieldsets.map((f) => {
                        const selected = (config[key] as string[]) ?? [];
                        return (
                            <label key={f.id} className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={selected.includes(f.handle)}
                                    onCheckedChange={(checked) =>
                                        onChange(checked ? [...selected, f.handle] : selected.filter((h) => h !== f.handle))
                                    }
                                />
                                {f.title}
                            </label>
                        );
                    })}
                </div>
            );
        case 'key_value': {
            // Options as textarea "value|Label" per line.
            const rows = (config[key] as { value: string; label: string }[]) ?? [];
            return (
                <textarea
                    className="min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                    placeholder="value|Label (one per line)"
                    defaultValue={rows.map((r) => `${r.value}|${r.label}`).join('\n')}
                    onBlur={(e) =>
                        onChange(
                            e.target.value.split('\n').map((line) => {
                                const [v, l] = line.split('|');
                                return { value: v?.trim() ?? '', label: (l ?? v)?.trim() ?? '' };
                            }).filter((r) => r.value !== ''),
                        )
                    }
                />
            );
        }
        default:
            return (
                <Input
                    value={String(config[key] ?? '')}
                    onChange={(e) => onChange(e.target.value)}
                />
            );
    }
}

function FieldRow({
    field, index, fieldTypes, fieldsets, depth, translatable, onUpdate, onRemove, dragHandle,
}: {
    /** Props for the drag handle (from useSortable). */
    dragHandle?: React.HTMLAttributes<HTMLButtonElement>;
    field: BuilderField;
    index: number;
    fieldTypes: FieldTypeDef[];
    fieldsets: Props['fieldsets'];
    depth: number;
    translatable: boolean;
    onUpdate: (patch: Partial<BuilderField>) => void;
    onRemove: () => void;
}) {
    const [open, setOpen] = React.useState(false);
    const typeDef = fieldTypes.find((t) => t.type === field.type);
    const isTranslatable = field.translatable ?? typeDef?.translatable ?? false;
    const switchId = `translatable-${depth}-${index}`;

    return (
        <div className="rounded-md border bg-card">
            <div className="flex items-center gap-2 px-2 py-1.5">
                <button type="button" className="cursor-grab touch-none rounded p-0.5 text-muted-foreground hover:text-foreground active:cursor-grabbing" aria-label="Drag to reorder" {...dragHandle}>
                    <GripVertical className="h-4 w-4" />
                </button>
                <button type="button" className="flex flex-1 items-center gap-2 text-left text-sm" onClick={() => setOpen(!open)}>
                    {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                    <span className="font-medium">{field.label || field.handle || '(unnamed)'}</span>
                    <span className="text-xs text-muted-foreground">{field.type}{includedFieldset(field) ? ` → ${includedFieldset(field)}` : ''}</span>
                </button>
                <button
                    type="button"
                    className="rounded p-0.5 text-muted-foreground hover:text-destructive"
                    aria-label={`Remove ${field.label || field.handle || 'field'}`}
                    onClick={() => window.confirm(`Remove the field "${field.label || field.handle}"? Saved content in it stays in the database but is no longer shown.`) && onRemove()}
                >
                    <Trash2 className="h-4 w-4" />
                </button>
            </div>
            {open && (
                <div className="grid gap-3 border-t p-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label className="text-xs">Handle</Label>
                            <Input value={field.handle} onChange={(e) => onUpdate({ handle: slugify(e.target.value) })} disabled={field.type === 'fieldset'} />
                        </div>
                        <div className="grid gap-1">
                            <Label className="text-xs">Label</Label>
                            <Input value={field.label ?? ''} onChange={(e) => onUpdate({ label: e.target.value })} />
                        </div>
                    </div>
                    <div className="flex items-center gap-4">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={!!field.required} onCheckedChange={(v) => onUpdate({ required: !!v })} />
                            Required
                        </label>
                        {translatable && field.type !== 'fieldset' && (
                            <label htmlFor={switchId} className="flex items-center gap-2 text-sm">
                                <Switch id={switchId} checked={isTranslatable} onCheckedChange={(v) => onUpdate({ translatable: v })} />
                                Translatable
                                <span className="text-xs text-muted-foreground">
                                    {isTranslatable
                                        ? CONTAINER_TYPES.includes(field.type) || field.type === 'flexible'
                                            ? 'each child field decides'
                                            : 'differs per language'
                                        : 'shared by all languages'}
                                </span>
                            </label>
                        )}
                    </div>
                    <div className="grid gap-1">
                        <Label className="text-xs">Instructions</Label>
                        <Input value={field.instructions ?? ''} onChange={(e) => onUpdate({ instructions: e.target.value })} />
                    </div>

                    {typeDef && typeDef.settings.length > 0 && (
                        <div className="grid gap-2 rounded-md bg-muted/40 p-2">
                            <span className="text-xs font-semibold uppercase text-muted-foreground">Settings</span>
                            {typeDef.settings.map((s) => (
                                <div key={settingKey(s)} className="grid gap-1">
                                    {s.type !== 'boolean' && s.type !== 'toggle' && <Label className="text-xs">{s.label}</Label>}
                                    <SettingControl
                                        def={s}
                                        config={field.config ?? {}}
                                        fieldsets={fieldsets}
                                        onChange={(v) => onUpdate({ config: { ...field.config, [settingKey(s)]: v as Json } })}
                                    />
                                </div>
                            ))}
                        </div>
                    )}

                    {CONTAINER_TYPES.includes(field.type) && (
                        <FieldBuilder
                            value={childFields(field)}
                            onChange={(fields) => onUpdate({ fields: undefined, config: { ...field.config, fields: fields as unknown as Json } })}
                            fieldTypes={fieldTypes}
                            fieldsets={fieldsets}
                            depth={depth + 1}
                            translatable={translatable}
                        />
                    )}
                </div>
            )}
        </div>
    );
}

function SortableField({ id, children }: { id: string; children: (dragHandle: React.HTMLAttributes<HTMLButtonElement>) => React.ReactNode }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });

    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={isDragging ? 'relative z-10 opacity-80 shadow-lg' : undefined}
        >
            {children({ ...attributes, ...listeners } as React.HTMLAttributes<HTMLButtonElement>)}
        </div>
    );
}

export default function FieldBuilder({ value, onChange, fieldTypes, fieldsets, depth = 0, translatable = false }: Props) {
    const add = (type: string) => {
        const next: BuilderField = {
            handle: `${type}_${value.length + 1}`,
            type,
            label: type.replace('_', ' '),
            required: false,
            config: {},
        };
        if (CONTAINER_TYPES.includes(type)) next.config = { fields: [] };
        if (type === 'flexible') next.config = { fieldsets: [] };
        if (type === 'fieldset') next.config = { fieldset: fieldsets[0]?.handle ?? '' };
        onChange([...value, next]);
    };

    // Stable ids for drag and drop: kept per field object, carried over on edits.
    const ids = React.useRef(new WeakMap<BuilderField, string>());
    const idOf = (field: BuilderField) => {
        let id = ids.current.get(field);
        if (!id) {
            id = Math.random().toString(36).slice(2);
            ids.current.set(field, id);
        }
        return id;
    };
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );
    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over || active.id === over.id) return;
        const from = value.findIndex((f) => idOf(f) === active.id);
        const to = value.findIndex((f) => idOf(f) === over.id);
        if (from >= 0 && to >= 0) onChange(arrayMove(value, from, to));
    };

    return (
        <div className="flex flex-col gap-2">
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                <SortableContext items={value.map(idOf)} strategy={verticalListSortingStrategy}>
                    {value.map((field, i) => (
                        <SortableField key={idOf(field)} id={idOf(field)}>
                            {(dragHandle) => (
                                <FieldRow
                                    field={field}
                                    index={i}
                                    fieldTypes={fieldTypes}
                                    fieldsets={fieldsets}
                                    depth={depth}
                                    translatable={translatable}
                                    dragHandle={dragHandle}
                                    onUpdate={(patch) => {
                                        const next = { ...field, ...patch };
                                        ids.current.set(next, idOf(field));
                                        onChange(value.map((f, j) => (j === i ? next : f)));
                                    }}
                                    onRemove={() => onChange(value.filter((_, j) => j !== i))}
                                />
                            )}
                        </SortableField>
                    ))}
                </SortableContext>
            </DndContext>

            <Select onValueChange={add}>
                <SelectTrigger className="w-48">
                    <SelectValue placeholder={<><Plus className="mr-1 inline h-4 w-4" />Add field</>} />
                </SelectTrigger>
                <SelectContent>
                    {fieldTypes.map((t) => (
                        <SelectItem key={t.type} value={t.type}>{t.type.replace('_', ' ')}</SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
