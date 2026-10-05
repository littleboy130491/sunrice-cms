import { Input } from '@/components/ui/input';
import type { FieldProps } from './types';

export default function TextField({ field, value, onChange }: FieldProps) {
    const maxLength = (field.config?.max as number | undefined) ?? undefined;

    return (
        <Input
            value={(value as string) ?? ''}
            maxLength={maxLength}
            onChange={(e) => onChange(e.target.value)}
        />
    );
}
