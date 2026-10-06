import FieldList from './FieldList';
import type { ContainerFieldProps } from './registry';
import type { Json } from '@/types';

export default function GroupField({ field, value, errors, pathPrefix, onChange }: ContainerFieldProps) {
    const values = (value as Record<string, Json>) ?? {};

    return (
        <div className="rounded-lg border border-border/80 bg-muted/25 p-4">
            <FieldList
                fields={field.fields ?? []}
                values={values}
                errors={errors}
                pathPrefix={pathPrefix}
                onChange={(handle, v) => onChange({ ...values, [handle]: v })}
            />
        </div>
    );
}
