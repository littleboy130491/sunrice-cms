import { Input } from '@/components/ui/input';
import type { ContainerFieldProps } from './registry';

/**
 * File field (frontend forms). Value is a File object — form
 * submissions post as multipart.
 */
export default function FileField({ field, onChange }: ContainerFieldProps) {
    return (
        <Input
            type="file"
            onChange={(e) => onChange(e.target.files?.[0] ?? null)}
            accept={field.config?.mimes ? String(field.config.mimes).split(',').map((m) => `.${m}`).join(',') : undefined}
        />
    );
}
