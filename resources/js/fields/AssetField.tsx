import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { FileText, ImagePlus, Plus, X } from 'lucide-react';
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
                    <div key={id} className="relative flex items-center gap-2.5 rounded-lg border border-input bg-card p-1.5 pr-2 text-sm shadow-xs">
                        {p?.is_image ? (
                            <img src={p.thumbnail || p.url} alt="" className="size-11 rounded-md bg-muted object-cover" />
                        ) : (
                            <span className="grid size-11 place-items-center rounded-md bg-muted">
                                <FileText className="size-5 text-muted-foreground" />
                            </span>
                        )}
                        <span className="max-w-40 truncate">{p?.filename ?? `Asset #${id}`}</span>
                        <button
                            type="button"
                            className="grid size-6 place-items-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                            onClick={() => remove(id)}
                            aria-label="Remove"
                        >
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
                        ids.length ? (
                            <Button type="button" variant="outline" size="sm">
                                <Plus className="mr-1 h-4 w-4" /> Add more
                            </Button>
                        ) : (
                            <button
                                type="button"
                                className="flex h-20 w-full items-center justify-center gap-2 rounded-lg border border-dashed border-input bg-muted/30 text-sm text-muted-foreground transition-colors outline-none hover:border-ring/50 hover:bg-muted/60 hover:text-foreground focus-visible:border-ring/70 focus-visible:ring-4 focus-visible:ring-ring/12"
                            >
                                <ImagePlus className="size-4" /> {imageOnly ? 'Choose image' : 'Choose asset'}
                            </button>
                        )
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
