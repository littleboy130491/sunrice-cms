import { Switch } from '@/components/ui/switch';
import type { FieldProps } from './types';

export default function ToggleField({ value, onChange }: FieldProps) {
    return <Switch checked={!!value} onCheckedChange={onChange} />;
}
