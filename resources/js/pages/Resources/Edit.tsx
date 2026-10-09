import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { adminUrl } from '@/lib/route';
import FieldRenderer from '@/fields/FieldRenderer';
import type { AdminField, SharedProps, Json } from '@/types';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';

interface RecordAction {
    key: string;
    label: string;
    confirm: string | null;
    destructive: boolean;
    // Set for actions that open an address (e.g. a PDF) instead of running on the server.
    url: string | null;
    new_tab: boolean;
}

interface Props {
    resource: { key: string; label: string; singularLabel: string };
    fields: AdminField[];
    record: Record<string, Json> | null;
    actions?: RecordAction[];
}

export default function ResourceEdit({ resource, fields, record, actions = [] }: Props) {
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

    const [running, setRunning] = React.useState<string | null>(null);
    function runAction(action: RecordAction) {
        if (action.confirm && !window.confirm(action.confirm)) return;
        router.post(adminUrl(`resources/${resource.key}/${(record as Record<string, Json>).id}/actions/${action.key}`, adminPath), {}, {
            preserveScroll: true,
            onStart: () => setRunning(action.key),
            onFinish: () => setRunning(null),
            // The action may have changed the record: show its new values.
            onSuccess: (page) => setValues(((page.props as unknown as Props).record ?? {}) as Record<string, Json>),
        });
    }

    // Ctrl/⌘ S saves.
    useUnsavedChanges(false, () => {
        if (!processing) save();
    });

    return (
        <div className="mx-auto max-w-3xl space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="sunrice-page-title">
                    {isNew ? `New ${resource.singularLabel}` : `Edit ${resource.singularLabel}`}
                </h1>
                {actions.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {actions.map((action) =>
                            action.url ? (
                                <Button key={action.key} variant={action.destructive ? 'destructive' : 'outline'} asChild>
                                    <a href={action.url} {...(action.new_tab ? { target: '_blank', rel: 'noopener' } : {})}>{action.label}</a>
                                </Button>
                            ) : (
                                <Button
                                    key={action.key}
                                    variant={action.destructive ? 'destructive' : 'outline'}
                                    disabled={running !== null}
                                    onClick={() => runAction(action)}
                                >
                                    {running === action.key ? `${action.label}…` : action.label}
                                </Button>
                            ),
                        )}
                    </div>
                )}
            </div>
            <CollapsibleCard title={resource.label} storageKey="resource:resource-label" hasErrors={Object.keys(errors).length > 0}>
                <FieldRenderer fields={fields} values={values} errors={errors} onChange={setValues} />
            </CollapsibleCard>
            <Button onClick={save} disabled={processing}>{processing ? 'Saving…' : isNew ? 'Create' : 'Save'}</Button>
        </div>
    );
}
