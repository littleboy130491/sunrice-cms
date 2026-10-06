import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FieldBuilder, { BuilderField, FieldTypeDef } from '@/components/field-builder/FieldBuilder';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

interface Props {
    blueprint: { id: number; handle: string; title: string; fields: BuilderField[] } | null;
    fieldTypes: FieldTypeDef[];
    fieldsets: { id: number; title: string; handle: string }[];
}

export default function BlueprintForm({ blueprint, fieldTypes, fieldsets }: Props) {
    const { adminPath, errors } = usePage<SharedProps>().props;
    const [title, setTitle] = React.useState(blueprint?.title ?? '');
    const [handle, setHandle] = React.useState(blueprint?.handle ?? '');
    const [fields, setFields] = React.useState<BuilderField[]>(blueprint?.fields ?? []);
    const [processing, setProcessing] = React.useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const payload = { title, handle, fields } as never;
        const opts = { onFinish: () => setProcessing(false) };
        setProcessing(true);
        if (blueprint) {
            router.put(adminUrl(`structure/blueprints/${blueprint.id}`, adminPath), payload, opts);
        } else {
            router.post(adminUrl('structure/blueprints', adminPath), payload, opts);
        }
    };

    return (
        <form onSubmit={submit} className="flex max-w-3xl flex-col gap-6">
            <h1 className="text-xl font-semibold tracking-tight">{blueprint ? `Edit ${blueprint.title}` : 'New blueprint'}</h1>
            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="title">Title</Label>
                    <Input id="title" value={title} onChange={(e) => {
                        setTitle(e.target.value);
                        if (!blueprint) setHandle(e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, ''));
                    }} required />
                    {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="handle">Handle</Label>
                    <Input id="handle" value={handle} onChange={(e) => setHandle(e.target.value)} required />
                    {errors.handle && <p className="text-sm text-destructive">{errors.handle}</p>}
                </div>
            </div>

            <div className="grid gap-2">
                <Label>Fields</Label>
                <FieldBuilder
                    value={fields}
                    onChange={setFields}
                    fieldTypes={fieldTypes}
                    fieldsets={fieldsets}
                />
            </div>

            <div>
                <Button type="submit" disabled={processing}>Save blueprint</Button>
            </div>
        </form>
    );
}
