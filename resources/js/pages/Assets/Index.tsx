import { Link, router, usePage } from '@inertiajs/react';
import * as React from 'react';
import { ChevronLeft, ChevronRight, FileText, Folder, Grid3X3, Image as ImageIcon, List, Pencil, Plus, Search, Trash2, Upload, Video } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';
import { xsrfToken } from '@/lib/fetch-json';

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
    version?: number;
}

interface FolderRow { id: number; parent_id: number | null; name: string; depth: number }
interface Paginated<T> { data: T[]; total: number; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null }

interface Props {
    assets: Paginated<AssetRow>;
    folders: FolderRow[];
    filters: { folder?: string; search?: string; type?: string; trashed?: string };
    maxUploadKb: number;
    allowedExtensions: string[];
    /** public/storage is missing, so uploaded files can't be shown. */
    storageLinkMissing?: boolean;
}

const formatKb = (kb: number) => (kb >= 1024 ? `${Math.round((kb / 1024) * 10) / 10} MB` : `${kb} KB`);

function iconFor(asset: AssetRow) {
    if (asset.mime_type?.startsWith('image/')) return <ImageIcon className="h-8 w-8 text-muted-foreground" />;
    if (asset.mime_type?.startsWith('video/')) return <Video className="h-8 w-8 text-muted-foreground" />;
    return <FileText className="h-8 w-8 text-muted-foreground" />;
}

function uploadFailure(status: number): string {
    if (status === 413) return 'the file is too large for this server.';
    if (status === 419) return 'your session expired. Reload the page and try again.';
    if (status === 403) return "you don't have permission to upload.";
    return `the upload failed (${status}).`;
}

export default function AssetsIndex({ assets, folders, filters, maxUploadKb, allowedExtensions, storageLinkMissing }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const [search, setSearch] = React.useState(filters.search ?? '');
    const [view, setView] = React.useState<'grid' | 'list'>('grid');
    const [checked, setChecked] = React.useState<Set<number>>(new Set());
    // A new page, folder or filter: drop selections the user can no longer see.
    React.useEffect(() => setChecked(new Set()), [assets.data]);
    const can = useCan();
    const canUpload = can('sunrice.assets.upload');
    const canEditAssets = can('sunrice.assets.edit');
    const canDeleteAssets = can('sunrice.assets.delete');
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

    const [uploading, setUploading] = React.useState(false);

    const [progress, setProgress] = React.useState<string | null>(null);

    // One request per file, one after another: a single request with every
    // file can exceed the server's post_max_size (8 MB by default) and fail
    // as a whole. Fetch (not Inertia visits, which cancel each other), then
    // reload the list once.
    const upload = async (files: FileList | null) => {
        if (!files?.length) return;
        const accepted: File[] = [];
        for (const file of Array.from(files)) {
            const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
            if (!allowedExtensions.includes(ext)) {
                toast.error(`${file.name}: .${ext} files can't be uploaded.`);
            } else if (file.size / 1024 > maxUploadKb) {
                toast.error(`${file.name} is larger than the ${formatKb(maxUploadKb)} limit.`);
            } else {
                accepted.push(file);
            }
        }
        if (accepted.length === 0) return;

        setUploading(true);
        let done = 0;
        for (const [i, file] of accepted.entries()) {
            if (accepted.length > 1) setProgress(`${i + 1}/${accepted.length}`);
            const data = new FormData();
            data.append('file', file);
            if (filters.folder) data.append('folder_id', filters.folder);
            try {
                const res = await fetch(adminUrl('assets', adminPath), {
                    method: 'POST',
                    body: data,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
                });
                if (res.ok) {
                    done++;
                    continue;
                }
                const json = await res.json().catch(() => ({}));
                const message = json.errors ? (Object.values(json.errors).flat()[0] as string) : json.message;
                toast.error(`${file.name}: ${message || uploadFailure(res.status)}`);
            } catch {
                toast.error(`${file.name}: the upload was interrupted. Check your connection and try again.`);
            }
        }
        setUploading(false);
        setProgress(null);

        if (done > 0) {
            toast.success(done === 1 ? `Uploaded ${accepted.length === 1 ? accepted[0].name : '1 file'}.` : `Uploaded ${done} files.`);
            router.reload({ only: ['assets', 'folders'] });
        }
    };

    const bulkTrash = () => {
        if (!window.confirm(`Move ${checked.size} ${checked.size === 1 ? 'asset' : 'assets'} to the trash?`)) return;
        router.post(adminUrl('assets/bulk-trash', adminPath), { ids: Array.from(checked) }, {
            preserveScroll: true,
            onSuccess: () => setChecked(new Set()),
        });
    };

    const deleteFolder = (f: FolderRow) => {
        if (!window.confirm(`Delete the folder "${f.name}"? It must be empty.`)) return;
        router.delete(adminUrl(`asset-folders/${f.id}`, adminPath), {
            preserveScroll: true,
            onSuccess: () => String(f.id) === filters.folder && pickFolder(null),
        });
    };

    const goToPage = (url: string | null) => url && router.get(url, {}, { preserveState: true, preserveScroll: false });

    return (
        <div className="flex flex-col gap-6 md:flex-row">
            <aside className="space-y-1 md:w-48 md:shrink-0">
                <div className="mb-2 flex items-center justify-between">
                    <span className="text-sm font-medium">Folders</span>
                    {canUpload && (
                        <Button variant="ghost" size="icon" className="h-6 w-6" aria-label="New folder" asChild>
                            <Link href={adminUrl(`asset-folders/create${filters.folder ? `?parent=${filters.folder}` : ''}`, adminPath)}><Plus className="h-4 w-4" /></Link>
                        </Button>
                    )}
                </div>
                <button
                    className={`flex w-full items-center gap-2 rounded px-2 py-1 text-sm ${!filters.folder ? 'bg-accent' : 'hover:bg-accent/50'}`}
                    onClick={() => pickFolder(null)}
                >
                    <Folder className="h-4 w-4" /> All assets
                </button>
                {folders.map((f) => (
                    <div key={f.id} className={`group flex items-center rounded ${String(f.id) === filters.folder ? 'bg-accent' : 'hover:bg-accent/50'}`}>
                        <button
                            className="flex min-w-0 flex-1 items-center gap-2 px-2 py-1 text-sm"
                            style={{ paddingLeft: `${8 + (f.depth ?? 0) * 12}px` }}
                            onClick={() => pickFolder(f.id)}
                        >
                            <Folder className="h-4 w-4 shrink-0" /> <span className="truncate">{f.name}</span>
                        </button>
                        {/* Always shown on touch screens, which have no hover. */}
                        {canEditAssets && <Link className="px-1 text-muted-foreground hover:text-foreground md:hidden md:group-hover:block" aria-label="Rename folder" href={adminUrl(`asset-folders/${f.id}/edit`, adminPath)}>
                            <Pencil className="h-3 w-3" />
                        </Link>}
                        {canDeleteAssets && <button className="px-1 text-muted-foreground hover:text-destructive md:hidden md:group-hover:block" aria-label="Delete folder" onClick={() => deleteFolder(f)}>
                            <Trash2 className="h-3 w-3" />
                        </button>}
                    </div>
                ))}
            </aside>

            <div className="min-w-0 flex-1 space-y-4">
                {storageLinkMissing && (
                    <p className="rounded-md border border-amber-500/50 bg-amber-500/10 p-3 text-sm">
                        Uploads are saved, but they can't be shown yet: the <code>public/storage</code> link is missing.
                        Run <code>php artisan storage:link</code> on the server.
                    </p>
                )}
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="relative w-full sm:w-72">
                        <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input className="pl-8" placeholder="Search assets…" value={search}
                            onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && visit()} />
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
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
                        <Button variant="ghost" size="icon" aria-label={view === 'grid' ? 'Show as list' : 'Show as grid'} onClick={() => setView(view === 'grid' ? 'list' : 'grid')}>
                            {view === 'grid' ? <List className="h-4 w-4" /> : <Grid3X3 className="h-4 w-4" />}
                        </Button>
                        {checked.size > 0 && canDeleteAssets && (
                            <Button variant="destructive" size="sm" onClick={bulkTrash}>
                                <Trash2 className="mr-1 h-4 w-4" /> Trash {checked.size}
                            </Button>
                        )}
                        {canUpload && <Button onClick={() => fileRef.current?.click()} disabled={uploading}>
                            <Upload className="mr-1 h-4 w-4" /> {uploading ? `Uploading${progress ? ` ${progress}` : ''}…` : 'Upload'}
                        </Button>}
                        <input
                            ref={fileRef}
                            type="file"
                            multiple
                            accept={allowedExtensions.map((e) => `.${e}`).join(',')}
                            className="hidden"
                            onChange={(e) => { upload(e.target.files); e.target.value = ''; }}
                        />
                    </div>
                </div>

                <div
                    className={`rounded-lg border-2 border-dashed p-4 ${dragging ? 'border-primary bg-accent/30' : 'border-transparent'}`}
                    onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                    onDragLeave={(e) => { if (!e.currentTarget.contains(e.relatedTarget as Node)) setDragging(false); }}
                    onDrop={(e) => { e.preventDefault(); setDragging(false); upload(e.dataTransfer.files); }}
                >
                    {view === 'grid' ? (
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
                            {assets.data.map((asset) => (
                                <div key={asset.id} className="group relative cursor-pointer rounded-md border p-2 hover:bg-accent/40"
                                    onClick={() => router.visit(adminUrl(`assets/${asset.id}/edit`, adminPath))}>
                                    <input type="checkbox" className="absolute left-2 top-2 z-10"
                                        aria-label={`Select ${asset.filename}`}
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
                                    <tr key={asset.id} className="cursor-pointer border-b hover:bg-accent/40" onClick={() => router.visit(adminUrl(`assets/${asset.id}/edit`, adminPath))}>
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
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>{assets.total} asset(s) · up to {formatKb(maxUploadKb)} per file</span>
                    {assets.last_page > 1 && (
                        <span className="flex items-center gap-2">
                            <Button variant="outline" size="icon" className="size-8" disabled={!assets.prev_page_url} onClick={() => goToPage(assets.prev_page_url)} aria-label="Previous page"><ChevronLeft /></Button>
                            Page {assets.current_page} of {assets.last_page}
                            <Button variant="outline" size="icon" className="size-8" disabled={!assets.next_page_url} onClick={() => goToPage(assets.next_page_url)} aria-label="Next page"><ChevronRight /></Button>
                        </span>
                    )}
                </div>
            </div>

        </div>
    );
}
