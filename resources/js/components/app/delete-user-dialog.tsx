import * as React from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { InputError } from '@/components/app/input-error';

export interface DeletableUser {
    id: number;
    name: string;
    email: string;
    content?: { entries: number; assets: number };
}

type ContentMode = 'reassign' | 'keep' | 'delete';

/** Deleting a user: choose what happens to the entries and files they created. */
export function DeleteUserDialog({ user, others, url, onClose }: { user: DeletableUser | null; others: DeletableUser[]; url: string; onClose: () => void }) {
    const [mode, setMode] = React.useState<ContentMode>('reassign');
    const [target, setTarget] = React.useState('');
    const [processing, setProcessing] = React.useState(false);
    const [error, setError] = React.useState<string | undefined>();

    React.useEffect(() => {
        setMode(others.length > 0 ? 'reassign' : 'keep');
        setTarget('');
        setError(undefined);
    }, [user?.id, others.length]);

    if (!user) return null;
    const entries = user.content?.entries ?? 0;
    const assets = user.content?.assets ?? 0;
    const hasContent = entries + assets > 0;
    const plural = (n: number, one: string, many: string) => `${n} ${n === 1 ? one : many}`;
    const owned = [entries > 0 && plural(entries, 'entry', 'entries'), assets > 0 && plural(assets, 'uploaded file', 'uploaded files')].filter(Boolean).join(' and ');

    const submit = () => {
        if (hasContent && mode === 'reassign' && !target) {
            setError('Choose who receives the content.');
            return;
        }
        router.delete(url, {
            data: hasContent ? { content: mode, reassign_to: mode === 'reassign' ? target : null } : { content: 'keep' },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: onClose,
            onError: (e) => setError(e.reassign_to ?? e.content),
        });
    };

    const option = (value: ContentMode, title: string, detail: React.ReactNode, danger = false) => (
        <label className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm transition-colors ${mode === value ? (danger ? 'border-destructive/60 bg-destructive/5' : 'border-ring/60 bg-muted/40') : 'border-border hover:bg-muted/30'}`}>
            <input type="radio" name="content-mode" className="mt-0.5 accent-current" checked={mode === value} onChange={() => setMode(value)} />
            <span className="grid gap-1">
                <span className={`font-medium ${danger ? 'text-destructive' : ''}`}>{title}</span>
                <span className="text-xs text-muted-foreground">{detail}</span>
            </span>
        </label>
    );

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Delete {user.name}?</DialogTitle>
                </DialogHeader>
                <div className="grid gap-3">
                    {hasContent ? (
                        <>
                            <p className="text-sm text-muted-foreground">
                                {user.email} created {owned}. What should happen to {entries + assets === 1 ? 'it' : 'them'}?
                            </p>
                            {others.length > 0 && option('reassign', 'Move it to another user', (
                                <span className="mt-1 block" onClick={(e) => e.preventDefault()}>
                                    <Select value={target} onValueChange={(v) => { setTarget(v); setMode('reassign'); setError(undefined); }}>
                                        <SelectTrigger className="w-full bg-card"><SelectValue placeholder="Choose a user…" /></SelectTrigger>
                                        <SelectContent>
                                            {others.map((o) => <SelectItem key={o.id} value={String(o.id)}>{o.name} ({o.email})</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </span>
                            ))}
                            {option('keep', 'Keep it without an author', 'Entries and files stay as they are; "Created by" shows no one.')}
                            {entries > 0 && option('delete', `Delete their ${plural(entries, 'entry', 'entries')}`, (
                                <>Moves {entries === 1 ? 'it' : 'them'} to the trash and off the site. They can be restored from each collection's trash until it is emptied. Uploaded files are kept.</>
                            ), true)}
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground">{user.email} hasn't created any entries or files. This can't be undone.</p>
                    )}
                    <InputError message={error} />
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="button" variant="destructive" disabled={processing} onClick={submit}>
                            {processing ? 'Deleting…' : mode === 'delete' && hasContent ? 'Delete user and entries' : 'Delete user'}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
