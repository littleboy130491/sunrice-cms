import * as React from 'react';
import { ChevronDown, ChevronRight, GripVertical, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Checkbox } from '@/components/ui/checkbox';

export interface BuilderField {
    handle: string;
    type: string;
    label?: string;
    required?: boolean;
    instructions?: string;
    conditions?: { field?: string; operator?: string; value?: string | number | boolean } | null;
    config?: { [key: string]: import('@/types').Json };
    fields?: BuilderField[];      // group / repeater children
    fieldsets?: string[];         // flexible allowed sets
    fieldset?: string;            // fieldset include
}

export interface FieldTypeDef {
    type: string;
    settings: { key: string; label: string; type: string; options?: unknown }[];
}

interface Props {
    value: BuilderField[];
    onChange: (fields: BuilderField[]) => void;
    fieldTypes: FieldTypeDef[];
    fieldsets: { id: number; title: string; handle: string }[];
    depth?: number;
}

const CONTAINER_TYPES = ['group', 'repeater'];

function slugify(input: string): string {
    return input.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
}

function SettingControl({
    def, config, onChange, fieldsets,
}: {
    def: FieldTypeDef['settings'][number];
    config: Record<string, unknown>;
    onChange: (v: unknown) => void;
    fieldsets: Props['fieldsets'];
}) {
    switch (def.type) {
        case 'boolean':
            return (
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox checked={!!config[def.key]} onCheckedChange={onChange} />
                    {def.label}
                </label>
            );
        case 'number':
            return (
                <Input type="number" value={(config[def.key] as number) ?? ''} onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))} />
            );
        case 'select':
            return (
                <Select value={String(config[def.key] ?? '')} onValueChange={onChange}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                        {((def.options as { value: string; label: string }[]) ?? []).map((o) => (
                            <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );
        case 'handles':
            return (
                <div className="flex flex-col gap-1">
                    {fieldsets.map((f) => {
                        const selected = (config[def.key] as string[]) ?? [];
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
            const rows = (config[def.key] as { value: string; label: string }[]) ?? [];
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
                    value={String(config[def.key] ?? '')}
                    onChange={(e) => onChange(e.target.value)}
                />
            );
    }
}

function FieldRow({
    field, index, fieldTypes, fieldsets, depth, onUpdate, onRemove,
}: {
    field: BuilderField;
    index: number;
    fieldTypes: FieldTypeDef[];
    fieldsets: Props['fieldsets'];
    depth: number;
    onUpdate: (patch: Partial<BuilderField>) => void;
    onRemove: () => void;
}) {
    const [open, setOpen] = React.useState(false);
    const typeDef = fieldTypes.find((t) => t.type === field.type);

    return (
        <div className="rounded-md border bg-card">
            <div className="flex items-center gap-2 px-2 py-1.5">
                <GripVertical className="h-4 w-4 text-muted-foreground" />
                <button type="button" className="flex flex-1 items-center gap-2 text-left text-sm" onClick={() => setOpen(!open)}>
                    {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                    <span className="font-medium">{field.label || field.handle || '(unnamed)'}</span>
                    <span className="text-xs text-muted-foreground">{field.type}{field.fieldset ? ` → ${field.fieldset}` : ''}</span>
                </button>
                <button type="button" className="text-muted-foreground hover:text-destructive" onClick={onRemove}>
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
                    </div>
                    <div className="grid gap-1">
                        <Label className="text-xs">Instructions</Label>
                        <Input value={field.instructions ?? ''} onChange={(e) => onUpdate({ instructions: e.target.value })} />
                    </div>

                    {typeDef && typeDef.settings.length > 0 && (
                        <div className="grid gap-2 rounded-md bg-muted/40 p-2">
                            <span className="text-xs font-semibold uppercase text-muted-foreground">Settings</span>
                            {typeDef.settings.map((s) => (
                                <div key={s.key} className="grid gap-1">
                                    {s.type !== 'boolean' && <Label className="text-xs">{s.label}</Label>}
                                    <SettingControl
                                        def={s}
                                        config={field.config ?? {}}
                                        fieldsets={fieldsets}
                                        onChange={(v) => onUpdate({ config: { ...field.config, [s.key]: v as import('@/types').Json } })}
                                    />
                                </div>
                            ))}
                        </div>
                    )}

                    {CONTAINER_TYPES.includes(field.type) && (
                        <FieldBuilder
                            value={field.fields ?? []}
                            onChange={(fields) => onUpdate({ fields })}
                            fieldTypes={fieldTypes}
                            fieldsets={fieldsets}
                            depth={depth + 1}
                        />
                    )}
                </div>
            )}
        </div>
    );
}

export default function FieldBuilder({ value, onChange, fieldTypes, fieldsets, depth = 0 }: Props) {
    const add = (type: string) => {
        const next: BuilderField = {
            handle: `${type}_${value.length + 1}`,
            type,
            label: type.replace('_', ' '),
            required: false,
            config: {},
        };
        if (CONTAINER_TYPES.includes(type)) next.fields = [];
        if (type === 'flexible') next.fieldsets = [];
        if (type === 'fieldset') next.fieldset = fieldsets[0]?.handle ?? '';
        onChange([...value, next]);
    };

    const update = (index: number, patch: Partial<BuilderField>) =>
        onChange(value.map((f, i) => (i === index ? { ...f, ...patch } : f)));

    const move = (index: number, dir: -1 | 1) => {
        const to = index + dir;
        if (to < 0 || to >= value.length) return;
        const next = [...value];
        [next[index], next[to]] = [next[to], next[index]];
        onChange(next);
    };

    return (
        <div className="flex flex-col gap-2">
            {value.map((field, i) => (
                <div key={i} className="flex items-start gap-1">
                    <div className="flex flex-col pt-1">
                        <button type="button" className="text-muted-foreground" onClick={() => move(i, -1)}>↑</button>
                        <button type="button" className="text-muted-foreground" onClick={() => move(i, 1)}>↓</button>
                    </div>
                    <div className="flex-1">
                        <FieldRow
                            field={field}
                            index={i}
                            fieldTypes={fieldTypes}
                            fieldsets={fieldsets}
                            depth={depth}
                            onUpdate={(patch) => update(i, patch)}
                            onRemove={() => onChange(value.filter((_, j) => j !== i))}
                        />
                    </div>
                </div>
            ))}

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
