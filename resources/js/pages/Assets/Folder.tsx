import * as React from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { InputError } from '@/components/app/input-error';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';

interface FolderRow { id: number; parent_id: number | null; name: string; depth: number }

interface Props {
    folder: { id: number; name: string; parent_id: number | null } | null;
    parentId: number | null;
    folders: FolderRow[];
}

/** Create or rename an asset folder. */
export default function AssetFolder({ folder, parentId, folders }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const isNew = folder === null;
    const form = useForm({ name: folder?.name ?? '', parent_id: parentId });
    const libraryUrl = adminUrl(`assets${parentId ? `?folder=${parentId}` : ''}`, adminPath);
    const parentName = folders.find((f) => f.id === parentId)?.name;
    useBreadcrumbs([{ label: 'Manage' }, { label: 'Assets', href: adminUrl('assets', adminPath) }, { label: isNew ? 'New folder' : folder.name }]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isNew) {
            form.post(adminUrl('asset-folders', adminPath));
        } else {
            form.put(adminUrl(`asset-folders/${folder.id}`, adminPath));
        }
    };

    const destroy = () => {
        if (folder && window.confirm(`Delete the folder "${folder.name}"? It must be empty.`)) {
            router.delete(adminUrl(`asset-folders/${folder.id}`, adminPath));
        }
    };

    // Leave-page warning for unsaved edits; Ctrl/⌘ S saves.
    const formRef = React.useRef<HTMLFormElement>(null);
    useUnsavedChanges(form.isDirty && !form.processing, () => formRef.current?.requestSubmit());

    return (
        <form ref={formRef} onSubmit={submit} className="flex max-w-2xl flex-col gap-6">
            <div className="flex items-start justify-between gap-4">
                <div className="flex items-start gap-3">
                    <Button variant="outline" size="icon" className="size-8 shrink-0" asChild>
                        <Link href={libraryUrl} aria-label="Back to assets"><ArrowLeft /></Link>
                    </Button>
                    <h1 className="sunrice-page-title">{isNew ? 'New folder' : `Rename ${folder.name}`}</h1>
                </div>
                <div className="flex items-center gap-2">
                    {!isNew && can('sunrice.assets.delete') && (
                        <Button type="button" variant="outline" size="icon" className="text-destructive" aria-label="Delete folder" onClick={destroy}><Trash2 /></Button>
                    )}
                    <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : isNew ? 'Create folder' : 'Save'}</Button>
                </div>
            </div>
            <CollapsibleCard title="Folder" storageKey="asset-folder" contentClassName="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="folder-name">Name</Label>
                    <Input id="folder-name" autoFocus value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    <InputError message={form.errors.name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="folder-parent">Inside</Label>
                    {isNew ? (
                        <Select value={form.data.parent_id === null ? 'root' : String(form.data.parent_id)} onValueChange={(v) => form.setData('parent_id', v === 'root' ? null : Number(v))}>
                            <SelectTrigger id="folder-parent" className="w-full"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="root">Top level</SelectItem>
                                {folders.map((f) => <SelectItem key={f.id} value={String(f.id)}>{'— '.repeat(f.depth ?? 0)}{f.name}</SelectItem>)}
                            </SelectContent>
                        </Select>
                    ) : (
                        <Input id="folder-parent" disabled value={parentName ?? 'Top level'} />
                    )}
                    <InputError message={form.errors.parent_id} />
                </div>
            </CollapsibleCard>
        </form>
    );
}
