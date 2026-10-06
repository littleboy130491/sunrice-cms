import { Image as ImageIcon, Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AssetPicker, { PickedAsset } from '@/components/AssetPicker';
import type { FieldProps } from './types';

export default function AssetField({ field, value, onChange }: FieldProps) {
    const multiple = !!field.config?.multiple;
    const imageOnly = !!field.config?.image_only;
    const ids: number[] = Array.isArray(value) ? (value as number[]) : typeof value === 'number' ? [value] : [];

    // Assets render as ids; thumbnails require a lookup, so ids are
    // shown alongside picked previews kept in state by the picker.
    const remove = (id: number) => onChange(ids.filter((v) => v !== id));

    return (
        <div className="flex flex-wrap items-center gap-2">
            {ids.map((id) => (
                <div key={id} className="relative flex items-center gap-2 rounded-md border p-2 text-sm">
                    <ImageIcon className="h-4 w-4 text-muted-foreground" />
                    <span>Asset #{id}</span>
                    <button type="button" className="text-muted-foreground hover:text-foreground" onClick={() => remove(id)}>
                        <X className="h-4 w-4" />
                    </button>
                </div>
            ))}
            <AssetPicker
                multiple={multiple}
                imageOnly={imageOnly}
                trigger={
                    <Button type="button" variant="outline" size="sm">
                        <Plus className="mr-1 h-4 w-4" /> {ids.length ? 'Add more' : 'Choose asset'}
                    </Button>
                }
                onSelect={(assets: PickedAsset[]) => {
                    const next = multiple ? [...ids, ...assets.map((a) => a.id)] : assets.slice(0, 1).map((a) => a.id);
                    onChange(multiple ? next : (next[0] ?? null));
                }}
            />
        </div>
    );
}
