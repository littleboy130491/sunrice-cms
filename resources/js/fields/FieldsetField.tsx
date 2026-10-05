import FieldList from './FieldList';
import type { ContainerFieldProps } from './registry';
import type { Json } from '@/types';

/**
 * A fieldset include renders its expanded children inline against the
 * same values object — the fields are flattened server-side into
 * `field.fields` by BlueprintSchema::toAdminSchema.
 */
export default function FieldsetField({ field, value, errors, pathPrefix, onChange }: ContainerFieldProps) {
    const values = (value as Record<string, Json>) ?? {};

    return (
        <FieldList
            fields={field.fields ?? []}
            values={values}
            errors={errors}
            pathPrefix={pathPrefix}
            onChange={(handle, v) => onChange({ ...values, [handle]: v })}
        />
    );
}
