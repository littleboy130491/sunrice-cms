import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { FieldProps } from './types';

export default function ToggleField({ field, value, onChange, pathPrefix }: FieldProps) {
    const id = `field-${pathPrefix ?? field.handle}`.replace(/[^a-zA-Z0-9_-]/g, '-');

    return (
        <div className="flex items-center gap-3">
            <Switch id={id} checked={!!value} onCheckedChange={onChange} />
            <Label htmlFor={id} className="font-normal">
                {field.label || field.handle}
                {field.required && <span className="text-destructive"> *</span>}
            </Label>
        </div>
    );
}
