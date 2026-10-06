import type { AdminField } from '@/types';

export interface FieldProps {
    field: AdminField;
    value: unknown;
    onChange: (value: unknown) => void;
    error?: string;
    /** Laravel dot path of this field's value, e.g. `data.sections.0.title`. */
    pathPrefix?: string;
}

export function errorFor(errors: Record<string, string> | undefined, path: string): string | undefined {
    return errors?.[path];
}
