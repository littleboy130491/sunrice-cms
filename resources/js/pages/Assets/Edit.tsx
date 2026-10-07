import * as React from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Copy, ExternalLink, FileText, Trash2, Undo2, Upload } from 'lucide-react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { InputError } from '@/components/app/input-error';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';
import type { SharedProps } from '@/types';

interface Asset {
    id: number;
    filename: string;
    mime_type: string;
    size: number;
    width: number | null;
    height: number | null;
    title: string | null;
    alt: string | null;
    caption: string | null;
    url: string;
    is_image: boolean;
    trashed: boolean;
    created_at: string | null;
}

interface Props {
    asset: Asset;
    folder: { id: number; name: string } | null;
    usages: { type: string; label: string; href: string | null }[];
}

const formatSize = (bytes: number) => (bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

/** One asset: preview, title / alt / caption, where it's used, replace, trash. */
export default function AssetEdit({ asset, folder, usages }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const editable = can('sunrice.assets.edit') && !asset.trashed;
    const canDelete = can('sunrice.assets.delete');
    const form = useForm({ title: asset.title ?? '', alt: asset.alt ?? '', caption: asset.caption ?? '' });
    const formRef = React.useRef<HTMLFormElement>(null);
    const fileRef = React.useRef<HTMLInputElement>(null);
    useUnsavedChanges(editable && form.isDirty && !form.processing, () => formRef.current?.requestSubmit());

    const libraryUrl = adminUrl(`assets${folder ? `?folder=${folder.id}` : ''}`, adminPath);
    useBreadcrumbs([{ label: 'Manage' }, { label: 'Assets', href: adminUrl('assets', adminPath) }, { label: asset.title || asset.filename }]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editable) return;
        form.put(adminUrl(`assets/${asset.id}`, adminPath), { preserveScroll: true, onSuccess: () => form.setDefaults() });
    };

    const replace = (file: File) => {
        const data = new FormData();
        data.append('file', file);
        router.post(adminUrl(`assets/${asset.id}/replace`, adminPath), data, { forceFormData: true, preserveScroll: true });
    };

    const copyUrl = async () => {
        try {
            await navigator.clipboard.writeText(new URL(asset.url, window.location.origin).toString());
            toast.success('Link copied.');
        } catch {
            toast.error('Could not copy. Open the file and copy its address instead.');
        }
    };

    const isRaster = asset.is_image && !asset.mime_type.includes('svg');

    return (
        <form ref={formRef} onSubmit={submit} className="flex flex-col gap-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex min-w-0 items-start gap-3">
                    <Button variant="outline" size="icon" className="size-8 shrink-0" asChild>
                        <Link href={libraryUrl} aria-label="Back to assets"><ArrowLeft /></Link>
                    </Button>
                    <div className="min-w-0 space-y-1">
                        <h1 className="truncate sunrice-page-title">{asset.title || asset.filename}</h1>
                        <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span className="truncate">{asset.filename}</span>
                            {folder && <span>· {folder.name}</span>}
                            {asset.trashed && <Badge variant="destructive">In trash</Badge>}
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {canDelete && !asset.trashed && (
                        <Button
                            type="button" variant="outline" size="icon" className="text-destructive" aria-label="Move to trash"
                            onClick={() => window.confirm(`Move ${asset.filename} to the trash?`) && router.delete(adminUrl(`assets/${asset.id}`, adminPath), { preserveScroll: true })}
                        ><Trash2 /></Button>
                    )}
                    {canDelete && asset.trashed && (
                        <>
                            <Button type="button" variant="outline" onClick={() => router.post(adminUrl(`assets/${asset.id}/restore`, adminPath), {}, { preserveScroll: true })}>
                                <Undo2 /> Restore
                            </Button>
                            <Button
                                type="button" variant="destructive"
                                onClick={() => window.confirm('Delete this file permanently? This can\'t be undone.') && router.delete(adminUrl(`assets/${asset.id}/force`, adminPath))}
                            >Delete forever</Button>
                        </>
                    )}
                    {editable && <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save'}</Button>}
                </div>
            </div>

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="flex min-w-0 flex-col gap-6">
                    <CollapsibleCard title="Preview" storageKey="asset:preview" contentClassName="flex flex-col gap-3">
                        <div className="flex min-h-48 items-center justify-center overflow-hidden rounded-lg bg-muted/60">
                            {isRaster || asset.mime_type.includes('svg')
                                ? <img src={asset.url} alt={asset.alt ?? ''} className="max-h-[28rem] w-auto object-contain" />
                                : <FileText className="size-12 text-muted-foreground" />}
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button type="button" variant="outline" size="sm" onClick={copyUrl}><Copy /> Copy link</Button>
                            <Button type="button" variant="outline" size="sm" asChild>
                                <a href={asset.url} target="_blank" rel="noreferrer"><ExternalLink /> Open file</a>
                            </Button>
                            {editable && (
                                <>
                                    <Button type="button" variant="outline" size="sm" onClick={() => fileRef.current?.click()}><Upload /> Replace file</Button>
                                    <input ref={fileRef} type="file" className="hidden" onChange={(e) => {
                                        const file = e.target.files?.[0];
                                        e.target.value = '';
                                        if (file && window.confirm(`Replace ${asset.filename} with ${file.name}? Pages using it show the new file.`)) replace(file);
                                    }} />
                                </>
                            )}
                        </div>
                    </CollapsibleCard>

                    <CollapsibleCard title="Details" storageKey="asset:details" contentClassName="flex flex-col gap-4">
                        <fieldset disabled={!editable} className="flex flex-col gap-4 disabled:opacity-70">
                            <div className="grid gap-2">
                                <Label htmlFor="asset-title">Title</Label>
                                <Input id="asset-title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                                <InputError message={form.errors.title} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="asset-alt">Alt text</Label>
                                <Input id="asset-alt" value={form.data.alt} onChange={(e) => form.setData('alt', e.target.value)} />
                                <p className="-mt-1 text-xs text-muted-foreground">Describes the image for screen readers and search engines.</p>
                                <InputError message={form.errors.alt} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="asset-caption">Caption</Label>
                                <Textarea id="asset-caption" value={form.data.caption} onChange={(e) => form.setData('caption', e.target.value)} />
                                <InputError message={form.errors.caption} />
                            </div>
                        </fieldset>
                    </CollapsibleCard>
                </div>

                <div className="flex flex-col gap-6 lg:sticky lg:top-6">
                    <CollapsibleCard title="File" titleClassName="text-sm" storageKey="asset:file">
                        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                            <dt className="text-muted-foreground">Type</dt><dd className="truncate">{asset.mime_type}</dd>
                            <dt className="text-muted-foreground">Size</dt><dd>{formatSize(asset.size)}</dd>
                            {asset.width && asset.height && (<><dt className="text-muted-foreground">Dimensions</dt><dd>{asset.width} × {asset.height}</dd></>)}
                            {asset.created_at && (<><dt className="text-muted-foreground">Uploaded</dt><dd>{new Date(asset.created_at).toLocaleDateString(undefined, { dateStyle: 'medium' })}</dd></>)}
                        </dl>
                    </CollapsibleCard>
                    <CollapsibleCard title="Used by" titleClassName="text-sm" storageKey="asset:usages">
                        {usages.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Not used in any entry, term or global yet.</p>
                        ) : (
                            <ul className="flex flex-col gap-2 text-sm">
                                {usages.map((u, i) => (
                                    <li key={i} className="flex flex-col">
                                        {u.href ? <Link href={u.href} className="font-medium hover:underline">{u.label}</Link> : <span className="font-medium">{u.label}</span>}
                                        <span className="text-xs text-muted-foreground">{u.type}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CollapsibleCard>
                    {editable && <p className="text-center text-xs text-muted-foreground">{form.isDirty ? 'Unsaved changes' : 'All changes saved'} · Ctrl/⌘ S</p>}
                </div>
            </div>
        </form>
    );
}
