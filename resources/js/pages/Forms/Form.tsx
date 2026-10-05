import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import FieldBuilder from '@/components/field-builder/FieldBuilder';
import type { BuilderField, FieldTypeDef } from '@/components/field-builder/FieldBuilder';
import { adminUrl } from '@/lib/route';
import type { SharedProps, Json } from '@/types';

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

    function save() {
        const payload = { handle, title, fields: fields as unknown as Json, settings };
        if (isNew) {
            router.post(adminUrl(adminPath, 'forms'), payload, { onError: setErrors });
        } else {
            router.put(adminUrl(adminPath, `forms/${form.id}`), payload, { onError: setErrors });
        }
    }

    return (
        <div className="mx-auto max-w-4xl space-y-6">
            <h1 className="text-xl font-semibold">{isNew ? 'New form' : `Form: ${form.title}`}</h1>
            <Card>
                <CardHeader><CardTitle>Details</CardTitle></CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-1">
                        <Label>Title</Label>
                        <Input value={title} onChange={(e) => setTitle(e.target.value)} />
                        {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                    </div>
                    <div className="space-y-1">
                        <Label>Handle</Label>
                        <Input value={handle} onChange={(e) => setHandle(e.target.value)} disabled={!isNew} />
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader><CardTitle>Fields</CardTitle></CardHeader>
                <CardContent>
                    <FieldBuilder value={fields} onChange={setFields} fieldTypes={fieldTypes} fieldsets={[]} />
                </CardContent>
            </Card>
            <Card>
                <CardHeader><CardTitle>Settings</CardTitle></CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-1">
                        <Label>Notify emails (comma separated)</Label>
                        <Input value={settings.notify_emails} onChange={(e) => setSettings({ ...settings, notify_emails: e.target.value })} />
                    </div>
                    <div className="space-y-1">
                        <Label>Success message</Label>
                        <Input value={settings.success_message} onChange={(e) => setSettings({ ...settings, success_message: e.target.value })} />
                    </div>
                    <div className="space-y-1">
                        <Label>Redirect URL (optional)</Label>
                        <Input value={settings.redirect_url} onChange={(e) => setSettings({ ...settings, redirect_url: e.target.value })} />
                    </div>
                </CardContent>
            </Card>
            <Button onClick={save}>Save form</Button>
        </div>
    );
}
