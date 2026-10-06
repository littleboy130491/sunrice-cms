import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { CollapsibleCard } from '@/components/app/collapsible-card';
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

    const [processing, setProcessing] = React.useState(false);

    function save() {
        const payload = { attributes: values };
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setErrors({}),
            // The server validates the attributes on their own; the field
            // list looks errors up under data.*.
            onError: (e: Record<string, string>) => setErrors(Object.fromEntries(Object.entries(e).map(([k, v]) => [`data.${k}`, v]))),
        };
        if (isNew) {
            router.post(adminUrl(`resources/${resource.key}`, adminPath), payload, options);
        } else {
            router.put(adminUrl(`resources/${resource.key}/${(record as Record<string, Json>).id}`, adminPath), payload, options);
        }
    }

    return (
        <div className="mx-auto max-w-3xl space-y-6">
            <h1 className="sunrice-page-title">
                {isNew ? `New ${resource.singularLabel}` : `Edit ${resource.singularLabel}`}
            </h1>
            <CollapsibleCard title={resource.label} storageKey="resource:resource-label" hasErrors={Object.keys(errors).length > 0}>
                <FieldRenderer fields={fields} values={values} errors={errors} onChange={setValues} />
            </CollapsibleCard>
            <Button onClick={save} disabled={processing}>{processing ? 'Saving…' : isNew ? 'Create' : 'Save'}</Button>
        </div>
    );
}
