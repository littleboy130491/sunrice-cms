import type { AdminField, Json } from '@/types';
import FieldList from './FieldList';

interface Props {
    fields: AdminField[];
    values: Record<string, Json>;
    errors?: Record<string, string>;
    onChange: (values: Record<string, Json>) => void;
}

/**
 * Top-level renderer for an admin-schema field list. Child field
 * components are looked up in the fieldComponents map; containers
 * recurse through FieldList.
 */
export default function FieldRenderer({ fields, values, errors, onChange }: Props) {
    return (
        <FieldList
            fields={fields}
            values={values}
            errors={errors}
            onChange={(handle, v) => onChange({ ...values, [handle]: v as Json })}
        />
    );
}
