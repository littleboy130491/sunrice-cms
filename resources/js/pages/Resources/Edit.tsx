import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { adminUrl } from '@/lib/route';
import FieldRenderer from '@/fields/FieldRenderer';
import type { AdminField, SharedProps, Json } from '@/types';

interface Props {
    resource: { key: string; label: string; singularLabel: string };
    fields: AdminField[];
    record: Record<string, Json> | null;
}

export default function ResourceEdit({ resource, fields, record }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const isNew = record === null;
    const [values, setValues] = React.useState<Record<string, Json>>(record ?? {});
    const [errors, setErrors] = React.useState<Record<string, string>>({});

    function save() {
        const payload = { attributes: values };
        if (isNew) {
            router.post(adminUrl(`resources/${resource.key}`, adminPath), payload, { onError: setErrors });
        } else {
            router.put(adminUrl(`resources/${resource.key}/${(record as Record<string, Json>).id}`, adminPath), payload, { onError: setErrors });
        }
    }

    return (
        <div className="mx-auto max-w-3xl space-y-6">
            <h1 className="text-xl font-semibold tracking-tight">
                {isNew ? `New ${resource.singularLabel}` : `Edit ${resource.singularLabel}`}
            </h1>
            <Card>
                <CardHeader><CardTitle>{resource.label}</CardTitle></CardHeader>
                <CardContent>
                    <FieldRenderer fields={fields} values={values} errors={errors} onChange={setValues} />
                </CardContent>
            </Card>
            <Button onClick={save}>{isNew ? 'Create' : 'Save'}</Button>
        </div>
    );
}
