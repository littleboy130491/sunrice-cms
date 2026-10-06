import { router, usePage } from '@inertiajs/react';
import * as React from 'react';
import { FileText, Folder, Grid3X3, Image as ImageIcon, List, Plus, Search, Trash2, Upload, Video } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

interface AssetRow {
    id: number;
    filename: string;
    mime_type: string;
    size: number;
    width: number | null;
    height: number | null;
    title: string | null;
    alt: string | null;
    caption: string | null;
    folder_id: number | null;
    url: string;
    thumbnail: string;
    trashed: boolean;
}

interface FolderRow { id: number; parent_id: number | null; name: string; path: string }
interface Paginated<T> { data: T[]; total: number }

interface Props {
    assets: Paginated<AssetRow>;
    folders: FolderRow[];
    filters: { folder?: string; search?: string; type?: string; trashed?: string };
}

function iconFor(asset: AssetRow) {
    if (asset.mime_type?.startsWith('image/')) return <ImageIcon className="h-8 w-8 text-muted-foreground" />;
    if (asset.mime_type?.startsWith('video/')) return <Video className="h-8 w-8 text-muted-foreground" />;
    return <FileText className="h-8 w-8 text-muted-foreground" />;
}

export default function AssetsIndex({ assets, folders, filters }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const [search, setSearch] = React.useState(filters.search ?? '');
    const [view, setView] = React.useState<'grid' | 'list'>('grid');
    const [detail, setDetail] = React.useState<AssetRow | null>(null);
    const [usages, setUsages] = React.useState<{ source_type: string; source_id: number }[]>([]);
    const [folderDialog, setFolderDialog] = React.useState(false);
    const [newFolder, setNewFolder] = React.useState('');
    const [checked, setChecked] = React.useState<Set<number>>(new Set());
    const [dragging, setDragging] = React.useState(false);
    const fileRef = React.useRef<HTMLInputElement>(null);

    const visit = (extra: Record<string, string | undefined> = {}) => {
        router.get(adminUrl('assets', adminPath), {
            folder: filters.folder,
            search: search || undefined,
            type: filters.type || undefined,
            trashed: filters.trashed || undefined,
            ...extra,
        }, { preserveState: true, replace: true });
    };

    const pickFolder = (id: number | null) =>
        router.get(adminUrl('assets', adminPath), { ...filters, folder: id ?? undefined }, { preserveState: true, replace: true });

    const upload = (files: FileList | null) => {
        if (!files?.length) return;
        Array.from(files).forEach((file) => {
            const data = new FormData();
            data.append('file', file);
            if (filters.folder) data.append('folder_id', filters.folder);
            router.post(adminUrl('assets', adminPath), data, { forceFormData: true, preserveScroll: true });
        });
    };

    const openDetail = async (asset: AssetRow) => {
        setDetail(asset);
        const res = await fetch(adminUrl(`assets/${asset.id}`, adminPath), { headers: { Accept: 'application/json' } });
        const json = await res.json();
        setUsages(json.usages ?? []);
    };

    const saveMeta = () => {
        if (!detail) return;
        router.put(adminUrl(`assets/${detail.id}`, adminPath), {
            title: detail.title, alt: detail.alt, caption: detail.caption,
        }, { preserveScroll: true, onSuccess: () => setDetail(null) });
    };

    const bulkTrash = () => {
        checked.forEach((id) => router.delete(adminUrl(`assets/${id}`, adminPath), { preserveScroll: true }));
        setChecked(new Set());
    };

    return (
        <div className="flex gap-6">
            <aside className="w-48 shrink-0 space-y-1">
                <div className="mb-2 flex items-center justify-between">
                    <span className="text-sm font-medium">Folders</span>
                    <Dialog open={folderDialog} onOpenChange={setFolderDialog}>
                        <DialogTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-6 w-6"><Plus className="h-4 w-4" /></Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader><DialogTitle>New folder</DialogTitle></DialogHeader>
                            <div className="flex flex-col gap-3">
                                <Input value={newFolder} onChange={(e) => setNewFolder(e.target.value)} placeholder="Folder name" />
                                <Button onClick={() => {
                                    router.post(adminUrl('asset-folders', adminPath), { name: newFolder, parent_id: filters.folder ? Number(filters.folder) : null }, { onSuccess: () => { setFolderDialog(false); setNewFolder(''); } });
                                }}>Create</Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                </div>
                <button
                    className={`flex w-full items-center gap-2 rounded px-2 py-1 text-sm ${!filters.folder ? 'bg-accent' : 'hover:bg-accent/50'}`}
                    onClick={() => pickFolder(null)}
                >
                    <Folder className="h-4 w-4" /> All assets
                </button>
                {folders.map((f) => (
                    <button
                        key={f.id}
                        className={`flex w-full items-center gap-2 rounded px-2 py-1 text-sm ${String(f.id) === filters.folder ? 'bg-accent' : 'hover:bg-accent/50'}`}
                        style={{ paddingLeft: `${8 + (f.path.match(/\//g)?.length ?? 0) * 12}px` }}
                        onClick={() => pickFolder(f.id)}
                    >
                        <Folder className="h-4 w-4" /> {f.name}
                    </button>
                ))}
            </aside>

            <div className="flex-1 space-y-4">
                <div className="flex items-center justify-between gap-2">
                    <div className="relative w-72">
                        <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input className="pl-8" placeholder="Search assets…" value={search}
                            onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && visit()} />
                    </div>
                    <div className="flex items-center gap-2">
                        <Select value={filters.type ?? 'all'} onValueChange={(v) => visit({ type: v === 'all' ? undefined : v })}>
                            <SelectTrigger className="w-32"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All types</SelectItem>
                                <SelectItem value="image">Images</SelectItem>
                                <SelectItem value="document">Documents</SelectItem>
                                <SelectItem value="video">Video</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={filters.trashed ?? 'active'} onValueChange={(v) => visit({ trashed: v === 'active' ? undefined : v })}>
                            <SelectTrigger className="w-32"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="with">With trashed</SelectItem>
                                <SelectItem value="only">Trashed only</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button variant="ghost" size="icon" onClick={() => setView(view === 'grid' ? 'list' : 'grid')}>
                            {view === 'grid' ? <List className="h-4 w-4" /> : <Grid3X3 className="h-4 w-4" />}
                        </Button>
                        {checked.size > 0 && (
                            <Button variant="destructive" size="sm" onClick={bulkTrash}>
                                <Trash2 className="mr-1 h-4 w-4" /> Trash {checked.size}
                            </Button>
                        )}
                        <Button onClick={() => fileRef.current?.click()}>
                            <Upload className="mr-1 h-4 w-4" /> Upload
                        </Button>
                        <input ref={fileRef} type="file" multiple className="hidden" onChange={(e) => upload(e.target.files)} />
                    </div>
                </div>

                <div
                    className={`rounded-lg border-2 border-dashed p-4 ${dragging ? 'border-primary bg-accent/30' : 'border-transparent'}`}
                    onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={(e) => { e.preventDefault(); setDragging(false); upload(e.dataTransfer.files); }}
                >
                    {view === 'grid' ? (
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
                            {assets.data.map((asset) => (
                                <div key={asset.id} className="group relative cursor-pointer rounded-md border p-2 hover:bg-accent/40"
                                    onClick={() => openDetail(asset)}>
                                    <input type="checkbox" className="absolute left-2 top-2 z-10"
                                        checked={checked.has(asset.id)}
                                        onClick={(e) => e.stopPropagation()}
                                        onChange={(e) => {
                                            const next = new Set(checked);
                                            e.target.checked ? next.add(asset.id) : next.delete(asset.id);
                                            setChecked(next);
                                        }} />
                                    <div className="flex h-24 items-center justify-center overflow-hidden rounded bg-muted">
                                        {asset.mime_type?.startsWith('image/') && !asset.mime_type.includes('svg')
                                            ? <img src={asset.thumbnail} alt={asset.alt ?? ''} className="h-full w-full object-cover" />
                                            : iconFor(asset)}
                                    </div>
                                    <div className="mt-1 truncate text-xs">{asset.title ?? asset.filename}</div>
                                    {asset.trashed && <Badge variant="destructive" className="absolute right-2 top-2">trashed</Badge>}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <table className="w-full text-sm">
                            <thead><tr className="border-b text-left"><th className="py-2">Name</th><th>Type</th><th>Size</th><th /></tr></thead>
                            <tbody>
                                {assets.data.map((asset) => (
                                    <tr key={asset.id} className="cursor-pointer border-b hover:bg-accent/40" onClick={() => openDetail(asset)}>
                                        <td className="py-2">{asset.title ?? asset.filename}</td>
                                        <td>{asset.mime_type}</td>
                                        <td>{Math.round(asset.size / 1024)} KB</td>
                                        <td />
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                    {assets.data.length === 0 && (
                        <div className="py-16 text-center text-sm text-muted-foreground">
                            No assets yet — drop files here or click Upload.
                        </div>
                    )}
                </div>
                <div className="text-sm text-muted-foreground">{assets.total} asset(s)</div>
            </div>

            <Sheet open={detail !== null} onOpenChange={(o) => !o && setDetail(null)}>
                <SheetContent className="w-96">
                    {detail && (
                        <>
                            <SheetHeader><SheetTitle>{detail.filename}</SheetTitle></SheetHeader>
                            <div className="mt-4 flex flex-col gap-4">
                                <div className="flex h-48 items-center justify-center overflow-hidden rounded bg-muted">
                                    {detail.mime_type?.startsWith('image/') && !detail.mime_type.includes('svg')
                                        ? <img src={detail.url} alt={detail.alt ?? ''} className="h-full w-full object-contain" />
                                        : iconFor(detail)}
                                </div>
                                <div className="grid gap-2">
                                    <Label>Title</Label>
                                    <Input value={detail.title ?? ''} onChange={(e) => setDetail({ ...detail, title: e.target.value })} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Alt text</Label>
                                    <Input value={detail.alt ?? ''} onChange={(e) => setDetail({ ...detail, alt: e.target.value })} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Caption</Label>
                                    <Input value={detail.caption ?? ''} onChange={(e) => setDetail({ ...detail, caption: e.target.value })} />
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {detail.width}×{detail.height} · {Math.round(detail.size / 1024)} KB
                                </div>
                                <div className="text-xs">
                                    <a href={detail.url} target="_blank" className="underline" rel="noreferrer">Copy / open URL</a>
                                </div>
                                {usages.length > 0 && (
                                    <div>
                                        <Label>Used by</Label>
                                        <ul className="mt-1 space-y-1 text-xs">
                                            {usages.map((u, i) => <li key={i}>{u.source_type} #{u.source_id}</li>)}
                                        </ul>
                                    </div>
                                )}
                                <div className="flex gap-2">
                                    <Button size="sm" onClick={saveMeta}>Save</Button>
                                    <label className="cursor-pointer">
                                        <Button size="sm" variant="outline" asChild><span>Replace file</span></Button>
                                        <input type="file" className="hidden" onChange={(e) => {
                                            const file = e.target.files?.[0];
                                            if (!file || !detail) return;
                                            const data = new FormData();
                                            data.append('file', file);
                                            router.post(adminUrl(`assets/${detail.id}/replace`, adminPath), data, { forceFormData: true, preserveScroll: true });
                                        }} />
                                    </label>
                                    {!detail.trashed && (
                                        <Button size="sm" variant="destructive" onClick={() => {
                                            router.delete(adminUrl(`assets/${detail.id}`, adminPath), { preserveScroll: true });
                                            setDetail(null);
                                        }}>Trash</Button>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </div>
    );
}
