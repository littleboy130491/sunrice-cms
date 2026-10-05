import type { AdminField } from '@/types';

export interface FieldProps {
    field: AdminField;
    value: unknown;
    onChange: (value: unknown) => void;
    error?: string;
}

export function errorFor(errors: Record<string, string> | undefined, path: string): string | undefined {
    return errors?.[path];
}
