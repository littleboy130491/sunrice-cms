import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { Folder, Image as ImageIcon, Search, Upload } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

export interface PickedAsset {
    id: number;
    filename: string;
    url: string;
    is_image: boolean;
}

interface Props {
    multiple?: boolean;
    imageOnly?: boolean;
    trigger: React.ReactNode;
    onSelect: (assets: PickedAsset[]) => void;
}

interface ApiAsset {
    id: number;
    filename: string;
    mime_type: string;
    url: string;
}

/**
 * Asset library dialog: folder navigation, search, upload. Returns
 * selected asset summaries to the caller.
 */
export default function AssetPicker({ multiple = false, imageOnly = false, trigger, onSelect }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const [open, setOpen] = React.useState(false);
    const [assets, setAssets] = React.useState<ApiAsset[]>([]);
    const [folders, setFolders] = React.useState<{ id: number; name: string }[]>([]);
    const [folderId, setFolderId] = React.useState<number | null>(null);
    const [search, setSearch] = React.useState('');
    const [selected, setSelected] = React.useState<Set<number>>(new Set());
    const fileRef = React.useRef<HTMLInputElement>(null);

    const load = React.useCallback(async () => {
        const params = new URLSearchParams();
        if (folderId) params.set('folder', String(folderId));
        if (search) params.set('search', search);
        if (imageOnly) params.set('images', '1');
        params.set('per_page', '60');
        const res = await fetch(`${adminUrl('assets', adminPath)}?${params}`, { headers: { Accept: 'application/json' } });
        const json = await res.json();
        setAssets(json.data ?? json.assets ?? []);
        setFolders(json.folders ?? []);
    }, [adminPath, folderId, search, imageOnly]);

    React.useEffect(() => {
        if (open) {
            const t = setTimeout(load, 200);
            return () => clearTimeout(t);
        }
    }, [open, load]);

    const upload = async (file: File) => {
        const form = new FormData();
        form.append('file', file);
        if (folderId) form.append('folder_id', String(folderId));
        const res = await fetch(adminUrl('assets', adminPath), {
            method: 'POST',
            body: form,
            headers: { 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') },
        });
        if (res.ok) load();
    };

    const choose = () => {
        onSelect(assets.filter((a) => selected.has(a.id)).map((a) => ({
            id: a.id, filename: a.filename, url: a.url, is_image: a.mime_type.startsWith('image/'),
        })));
        setOpen(false);
        setSelected(new Set());
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Asset library</DialogTitle>
                </DialogHeader>
                <div className="flex items-center gap-2">
                    <div className="relative flex-1">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input className="pl-8" placeholder="Search…" value={search} onChange={(e) => setSearch(e.target.value)} />
                    </div>
                    <Button variant="outline" onClick={() => fileRef.current?.click()}>
                        <Upload className="mr-1 h-4 w-4" /> Upload
                    </Button>
                    <input
                        ref={fileRef}
                        type="file"
                        className="hidden"
                        onChange={(e) => e.target.files?.[0] && upload(e.target.files[0])}
                    />
                </div>
                <div className="flex gap-2 text-sm">
                    <button className={!folderId ? 'font-medium' : 'text-muted-foreground'} onClick={() => setFolderId(null)}>
                        All
                    </button>
                    {folders.map((f) => (
                        <button
                            key={f.id}
                            className={folderId === f.id ? 'font-medium' : 'text-muted-foreground'}
                            onClick={() => setFolderId(f.id)}
                        >
                            <Folder className="mr-1 inline h-3 w-3" />
                            {f.name}
                        </button>
                    ))}
                </div>
                <div className="grid max-h-80 grid-cols-4 gap-3 overflow-y-auto sm:grid-cols-5">
                    {assets.map((a) => {
                        const isImage = a.mime_type.startsWith('image/');
                        return (
                            <button
                                key={a.id}
                                className={`flex flex-col items-center gap-1 rounded-md border p-2 text-xs ${selected.has(a.id) ? 'border-primary ring-1 ring-primary' : ''}`}
                                onClick={() => {
                                    setSelected((s) => {
                                        const next = multiple ? new Set(s) : new Set<number>();
                                        if (s.has(a.id)) next.delete(a.id);
                                        else next.add(a.id);
                                        return next;
                                    });
                                    if (!multiple) {
                                        setSelected(new Set([a.id]));
                                    }
                                }}
                            >
                                {isImage ? (
                                    <img src={a.url} alt={a.filename} className="h-16 w-full rounded object-cover" />
                                ) : (
                                    <div className="flex h-16 w-full items-center justify-center rounded bg-muted">
                                        <ImageIcon className="h-6 w-6 text-muted-foreground" />
                                    </div>
                                )}
                                <span className="w-full truncate">{a.filename}</span>
                            </button>
                        );
                    })}
                    {assets.length === 0 && <p className="col-span-full py-8 text-center text-sm text-muted-foreground">No assets.</p>}
                </div>
                <div className="flex justify-end gap-2">
                    <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
                    <Button disabled={selected.size === 0} onClick={choose}>
                        Select {selected.size > 0 ? `(${selected.size})` : ''}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
