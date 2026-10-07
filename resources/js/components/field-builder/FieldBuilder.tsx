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
        case 'file_types':
            return <FileTypesControl value={(config[key] as string[] | undefined) ?? []} options={def.options as FileTypesOptions} onChange={onChange} />;
        case 'file_size':
            return <FileSizeControl value={(config[key] as number | null | undefined) ?? null} options={def.options as FileSizeOptions} onChange={onChange} />;
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

interface FileTypesOptions {
    groups: { key: string; label: string; extensions: string[] }[];
    blocked: string[];
}

interface FileSizeOptions {
    default_kb: number;
    server_kb: number | null;
    upload_max_filesize: string;
    post_max_size: string;
}

const formatKb = (kb: number) => (kb >= 1024 ? `${Math.round((kb / 1024) * 10) / 10} MB` : `${kb} KB`);

/** Accepted upload types: common groups plus any other extensions. */
function FileTypesControl({ value, options, onChange }: { value: string[]; options: FileTypesOptions; onChange: (v: unknown) => void }) {
    const selected = value.map((e) => e.toLowerCase().replace(/^\./, ''));
    const grouped = new Set(options.groups.flatMap((g) => g.extensions));
    const extra = selected.filter((e) => !grouped.has(e));
    const [extraText, setExtraText] = React.useState(extra.join(', '));

    const setGroup = (extensions: string[], on: boolean) => {
        const next = on ? [...new Set([...selected, ...extensions])] : selected.filter((e) => !extensions.includes(e));
        onChange(next);
    };
    const applyExtra = (text: string) => {
        const typed = text.split(/[\s,]+/).map((e) => e.toLowerCase().replace(/^\./, '')).filter((e) => /^[a-z0-9]{1,10}$/.test(e) && !options.blocked.includes(e));
        onChange([...new Set([...selected.filter((e) => grouped.has(e)), ...typed])]);
    };
    const blockedTyped = extraText.split(/[\s,]+/).map((e) => e.toLowerCase().replace(/^\./, '')).filter((e) => options.blocked.includes(e));

    return (
        <div className="grid gap-2">
            <div className="grid gap-1 sm:grid-cols-2">
                {options.groups.map((g) => {
                    const on = g.extensions.every((e) => selected.includes(e));
                    const some = !on && g.extensions.some((e) => selected.includes(e));
                    return (
                        <label key={g.key} className="flex items-start gap-2 text-sm">
                            <Checkbox className="mt-0.5" checked={on ? true : some ? 'indeterminate' : false} onCheckedChange={(c) => setGroup(g.extensions, c === true)} />
                            <span>
                                {g.label}
                                <span className="block text-xs text-muted-foreground">{g.extensions.map((e) => `.${e}`).join(' ')}</span>
                            </span>
                        </label>
                    );
                })}
            </div>
            <div className="grid gap-1">
                <span className="text-xs text-muted-foreground">Other extensions (comma separated)</span>
                <Input value={extraText} placeholder="e.g. dwg, psd" onChange={(e) => setExtraText(e.target.value)} onBlur={(e) => applyExtra(e.target.value)} />
            </div>
            {blockedTyped.length > 0 && (
                <p className="text-xs text-destructive">{blockedTyped.map((e) => `.${e}`).join(', ')} can't be accepted: these files could run as code.</p>
            )}
            <p className="text-xs text-muted-foreground">
                {selected.length === 0
                    ? 'Nothing ticked: any file type is accepted, except ones that could run as code (.php, .html, .js, .exe…).'
                    : `Accepted: ${selected.map((e) => `.${e}`).join(', ')}. Visitors' file pickers show only these.`}
            </p>
        </div>
    );
}

/** Maximum upload size in MB (stored in KB), next to this server's own limit. */
function FileSizeControl({ value, options, onChange }: { value: number | null; options: FileSizeOptions; onChange: (v: unknown) => void }) {
    const effective = value ?? options.default_kb;
    const overServer = options.server_kb !== null && effective > options.server_kb;

    return (
        <div className="grid gap-1.5">
            <div className="flex items-center gap-2">
                <Input
                    type="number"
                    min={0.1}
                    step={0.5}
                    className="w-32"
                    value={value === null ? '' : Math.round((value / 1024) * 100) / 100}
                    placeholder={String(Math.round((options.default_kb / 1024) * 100) / 100)}
                    onChange={(e) => onChange(e.target.value === '' ? null : Math.max(1, Math.round(Number(e.target.value) * 1024)))}
                />
                <span className="text-sm text-muted-foreground">MB</span>
            </div>
            <p className="text-xs text-muted-foreground">
                Empty uses the site default ({formatKb(options.default_kb)}, <code>sunrice.forms.upload_max_kb</code>).
            </p>
            <p className="text-xs text-muted-foreground">
                This server accepts uploads up to <strong>{options.server_kb === null ? 'no set limit' : formatKb(options.server_kb)}</strong>
                {' '}(PHP <code>upload_max_filesize</code> = {options.upload_max_filesize || '—'}, <code>post_max_size</code> = {options.post_max_size || '—'}).
            </p>
            {overServer && (
                <p className="text-xs text-amber-700 dark:text-amber-400">
                    That's more than the server accepts, so files over {formatKb(options.server_kb!)} will still be refused. Raise
                    upload_max_filesize and post_max_size in PHP's settings (php.ini) to allow larger files.
                </p>
            )}
        </div>
    );
}

function FieldRow({
    field, index, fieldTypes, fieldsets, depth, translatable, onUpdate, onRemove, dragHandle, saved = false,
}: {
    /** The field existed when the page loaded (content may be stored under its handle). */
    saved?: boolean;
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
    const originalHandle = React.useRef(field.handle);
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
                            {saved && field.handle !== originalHandle.current && (
                                <p className="text-xs text-amber-600">
                                    Content saved under “{originalHandle.current}” won't show under the new name. To move it, keep the old name here and run
                                    {' '}<code>php artisan sunrice:rename-field</code>.
                                </p>
                            )}
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

/** `text_1`, `text_2`…: the first free handle for a new field of this type. */
function uniqueHandle(type: string, fields: BuilderField[]): string {
    const taken = new Set(fields.map((f) => f.handle));
    let n = 1;
    while (taken.has(`${type}_${n}`)) n++;
    return `${type}_${n}`;
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
            handle: uniqueHandle(type, value),
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
    // Fields present on load; renaming one of those can orphan saved content.
    const initialIds = React.useRef<Set<string> | null>(null);
    if (initialIds.current === null) initialIds.current = new Set(value.map(idOf));
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
                                    saved={initialIds.current?.has(idOf(field)) ?? false}
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

            {/* Controlled and reset to empty, so the same type can be added twice in a row. */}
            <Select value="" onValueChange={add}>
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
