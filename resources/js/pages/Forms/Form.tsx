import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import FieldBuilder from '@/components/field-builder/FieldBuilder';
import type { BuilderField, FieldTypeDef } from '@/components/field-builder/FieldBuilder';
import { adminUrl } from '@/lib/route';
import { InputError } from '@/components/app/input-error';
import type { SharedProps, Json } from '@/types';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';

interface Props {
    form: {
        id: number;
        handle: string;
        title: string;
        fields: BuilderField[];
        settings: Record<string, string>;
    } | null;
    fieldTypes: FieldTypeDef[];
}

export default function FormEditor({ form, fieldTypes }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const isNew = form === null;

    const [handle, setHandle] = React.useState(form?.handle ?? '');
    const [title, setTitle] = React.useState(form?.title ?? '');
    const [fields, setFields] = React.useState<BuilderField[]>(form?.fields ?? []);
    const [settings, setSettings] = React.useState({
        notify_emails: form?.settings?.notify_emails ?? '',
        success_message: form?.settings?.success_message ?? '',
        redirect_url: form?.settings?.redirect_url ?? '',
    });
    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [processing, setProcessing] = React.useState(false);

    function save() {
        const payload = { handle, title, fields: fields as unknown as Json, settings };
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setErrors({}),
            onError: (e: Record<string, string>) => setErrors(e),
        };
        if (isNew) {
            router.post(adminUrl('forms', adminPath), payload, options);
        } else {
            router.put(adminUrl(`forms/${form.id}`, adminPath), payload, options);
        }
    }
    // Field errors come back as fields.{index}.{key}; show them by field.
    const fieldErrors = Object.entries(errors)
        .filter(([k]) => k === 'fields' || k.startsWith('fields.'))
        .map(([k, v]) => {
            const index = Number(k.split('.')[1]);
            const field = Number.isNaN(index) ? null : fields[index];
            return field ? `${field.label || field.handle || `Field ${index + 1}`}: ${v}` : v;
        });
    const settingsError = (key: string) => errors[`settings.${key}`];

    // Ctrl/⌘ S saves.
    useUnsavedChanges(false, () => {
        if (!processing) save();
    });

    return (
        <div className="mx-auto max-w-4xl space-y-6">
            <h1 className="sunrice-page-title">{isNew ? 'New form' : `Form: ${form.title}`}</h1>
            <CollapsibleCard title="Details" storageKey="form:details" hasErrors={Object.keys(errors).length > 0} contentClassName="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1">
                    <Label>Title</Label>
                    <Input value={title} onChange={(e) => setTitle(e.target.value)} />
                    <InputError message={errors.title} />
                </div>
                <div className="space-y-1">
                    <Label>Handle</Label>
                    <Input value={handle} onChange={(e) => setHandle(e.target.value)} disabled={!isNew} />
                    <InputError message={errors.handle} />
                </div>
            </CollapsibleCard>
            <CollapsibleCard title="Fields" storageKey="form:fields" hasErrors={Object.keys(errors).length > 0}>
                {fieldErrors.length > 0 && (
                    <div className="mb-3 rounded-md border border-destructive/50 p-3 text-sm text-destructive">
                        {fieldErrors.map((m) => <p key={m}>{m}</p>)}
                    </div>
                )}
                <FieldBuilder value={fields} onChange={setFields} fieldTypes={fieldTypes} fieldsets={[]} />
            </CollapsibleCard>
            <CollapsibleCard title="Settings" storageKey="form:settings" hasErrors={Object.keys(errors).length > 0} contentClassName="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1">
                    <Label>Notify emails (comma separated)</Label>
                    <Input value={settings.notify_emails} onChange={(e) => setSettings({ ...settings, notify_emails: e.target.value })} />
                    <InputError message={settingsError('notify_emails')} />
                </div>
                <div className="space-y-1">
                    <Label>Success message</Label>
                    <Input value={settings.success_message} onChange={(e) => setSettings({ ...settings, success_message: e.target.value })} />
                    <InputError message={settingsError('success_message')} />
                </div>
                <div className="space-y-1">
                    <Label>Redirect URL (optional)</Label>
                    <Input value={settings.redirect_url} onChange={(e) => setSettings({ ...settings, redirect_url: e.target.value })} />
                    <InputError message={settingsError('redirect_url')} />
                </div>
            </CollapsibleCard>
            <Button onClick={save} disabled={processing}>{processing ? 'Saving…' : 'Save form'}</Button>
        </div>
    );
}
