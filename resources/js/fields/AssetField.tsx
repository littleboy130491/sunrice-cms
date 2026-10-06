import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { FileText, Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AssetPicker, { PickedAsset } from '@/components/AssetPicker';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';
import type { FieldProps } from './types';

interface Preview {
    id: number;
    filename: string;
    thumbnail?: string;
    url: string;
    is_image: boolean;
}

export default function AssetField({ field, value, onChange }: FieldProps) {
    const { adminPath } = usePage<SharedProps>().props;
    const multiple = !!field.config?.multiple;
    const imageOnly = !!field.config?.image_only;
    const ids: number[] = Array.isArray(value) ? (value as number[]) : typeof value === 'number' ? [value] : [];
    const [previews, setPreviews] = React.useState<Record<number, Preview>>({});

    // Thumbnails and names for the stored ids.
    const missing = ids.filter((id) => !previews[id]).join(',');
    React.useEffect(() => {
        if (!missing) return;
        fetch(`${adminUrl('api/assets', adminPath)}?ids=${missing}`, { headers: { Accept: 'application/json' } })
            .then((res) => (res.ok ? res.json() : null))
            .then((json) => {
                const rows: Preview[] = json?.data?.data ?? json?.data ?? [];
                if (rows.length) setPreviews((p) => ({ ...p, ...Object.fromEntries(rows.map((r) => [r.id, r])) }));
            })
            .catch(() => undefined);
    }, [missing, adminPath]);

    const emit = (next: number[]) => onChange(multiple ? next : (next[0] ?? null));
    const remove = (id: number) => emit(ids.filter((v) => v !== id));

    return (
        <div className="flex flex-wrap items-center gap-2">
            {ids.map((id) => {
                const p = previews[id];
                return (
                    <div key={id} className="relative flex items-center gap-2 rounded-md border p-2 text-sm">
                        {p?.is_image ? (
                            <img src={p.thumbnail || p.url} alt="" className="size-10 rounded object-cover" />
                        ) : (
                            <FileText className="size-5 text-muted-foreground" />
                        )}
                        <span className="max-w-40 truncate">{p?.filename ?? `Asset #${id}`}</span>
                        <button type="button" className="text-muted-foreground hover:text-foreground" onClick={() => remove(id)} aria-label="Remove">
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                );
            })}
            {(multiple || ids.length === 0) && (
                <AssetPicker
                    multiple={multiple}
                    imageOnly={imageOnly}
                    trigger={
                        <Button type="button" variant="outline" size="sm">
                            <Plus className="mr-1 h-4 w-4" /> {ids.length ? 'Add more' : 'Choose asset'}
                        </Button>
                    }
                    onSelect={(assets: PickedAsset[]) => {
                        setPreviews((p) => ({
                            ...p,
                            ...Object.fromEntries(assets.map((a) => [a.id, { id: a.id, filename: a.filename, url: a.url, is_image: a.is_image }])),
                        }));
                        emit(multiple ? [...ids, ...assets.map((a) => a.id).filter((id) => !ids.includes(id))] : assets.slice(0, 1).map((a) => a.id));
                    }}
                />
            )}
            {!multiple && ids.length > 0 && (
                <AssetPicker
                    imageOnly={imageOnly}
                    trigger={<Button type="button" variant="ghost" size="sm">Change</Button>}
                    onSelect={(assets: PickedAsset[]) => {
                        const a = assets[0];
                        if (!a) return;
                        setPreviews((p) => ({ ...p, [a.id]: { id: a.id, filename: a.filename, url: a.url, is_image: a.is_image } }));
                        emit([a.id]);
                    }}
                />
            )}
        </div>
    );
}
