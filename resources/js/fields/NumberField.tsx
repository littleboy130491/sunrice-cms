import { Input } from '@/components/ui/input';
import type { FieldProps } from './types';

export default function NumberField({ field, value, onChange }: FieldProps) {
    return (
        <Input
            type="number"
            value={value === null || value === undefined ? '' : String(value)}
            min={field.config?.min as number | undefined}
            max={field.config?.max as number | undefined}
            onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
        />
    );
}
