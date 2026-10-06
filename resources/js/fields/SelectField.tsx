import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { FieldProps } from './types';

export default function SelectField({ field, value, onChange }: FieldProps) {
    const options = (field.config?.options as { value: string; label: string }[] | string[] | undefined) ?? [];
    const normalized = options.map((o) => (typeof o === 'string' ? { value: o, label: o } : o));
    const multiple = !!field.config?.multiple;

    if (multiple) {
        const selected = (value as string[]) ?? [];
        return (
            <div className="flex flex-col gap-2">
                {normalized.map((o) => (
                    <label key={o.value} className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={selected.includes(o.value)}
                            onCheckedChange={(checked) =>
                                onChange(checked ? [...selected, o.value] : selected.filter((v) => v !== o.value))
                            }
                        />
                        {o.label}
                    </label>
                ))}
            </div>
        );
    }

    return (
        <Select value={(value as string) ?? ''} onValueChange={onChange}>
            <SelectTrigger>
                <SelectValue placeholder="Select…" />
            </SelectTrigger>
            <SelectContent>
                {normalized.map((o) => (
                    <SelectItem key={o.value} value={o.value}>
                        {o.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
