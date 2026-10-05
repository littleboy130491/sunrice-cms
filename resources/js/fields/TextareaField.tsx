import { Textarea } from '@/components/ui/textarea';
import type { FieldProps } from './types';

export default function TextareaField({ value, onChange }: FieldProps) {
    return <Textarea value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)} />;
}
