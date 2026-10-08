import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { History, Lock, UserRound } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    AlertDialog, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { fetchJson, xsrfToken } from '@/lib/fetch-json';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

export interface KeptEdit { id: number; by: string | null; taken_by: string | null; at: string | null }

type LockState =
    | { status: 'starting' }
    | { status: 'holding' }
    | { status: 'locked'; by: string; since: number; confirming: boolean }
    | { status: 'viewing'; by: string }
    | { status: 'taken'; by: string };

interface Options {
    type: 'entry' | 'term' | 'global';
    id: number | null | undefined;
    /** Language being edited (entries, translatable globals). */
    locale?: string;
    /** Off for new items and for people who can only view. */
    enabled: boolean;
    /** Unsaved changes to keep if someone takes over, or null when there are none. */
    getUnsaved: () => Record<string, unknown> | null;
    /** Where to go after being taken over. */
    leaveTo: string;
}

const RENEW_EVERY = 15_000;

/** One id per browser tab, kept across reloads, so a tab recognises its own lock. */
function editorId(): string {
    try {
        let id = window.sessionStorage.getItem('sunrice-editor-id');
        if (!id) {
            id = Math.random().toString(36).slice(2) + Date.now().toString(36);
            window.sessionStorage.setItem('sunrice-editor-id', id);
        }
        return id;
    } catch {
        return 'tab-' + Math.random().toString(36).slice(2);
    }
}

/**
 * WordPress-style edit lock for an editor page: holds the lock while the
 * page is open, tells the user when someone else is editing (go back,
 * view only, or take over), and hands over cleanly when taken over.
 */
export function useEditLock({ type, id, locale = '', enabled, getUnsaved, leaveTo }: Options) {
    const { adminPath } = usePage<SharedProps>().props;
    const [state, setState] = React.useState<LockState>({ status: 'starting' });
    const [kept, setKept] = React.useState<KeptEdit[]>([]);
    const editor = React.useMemo(editorId, []);
    const unsavedRef = React.useRef(getUnsaved);
    unsavedRef.current = getUnsaved;
    const base = id ? adminUrl(`locks/${type}/${id}`, adminPath) : '';

    const post = React.useCallback(async (path: string, body: Record<string, unknown> = {}, keepalive = false) => {
        const res = await fetch(base + path, {
            method: 'POST',
            keepalive,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
            body: JSON.stringify({ editor, locale, ...body }),
        });
        if (!res.ok) throw new Error(String(res.status));
        return res.json();
    }, [base, editor, locale]);

    const stateRef = React.useRef(state);
    stateRef.current = state;

    const renew = React.useCallback(async () => {
        const result = await post('').catch(() => null);
        if (!result) return;
        setKept(result.kept ?? []);
        if (result.status === 'ok') {
            setState({ status: 'holding' });
        } else if (result.status === 'locked') {
            // Viewing stays viewing; otherwise ask what to do.
            if (stateRef.current.status !== 'viewing') setState({ status: 'locked', by: result.by, since: result.since, confirming: false });
        } else if (result.status === 'taken' && stateRef.current.status !== 'taken') {
            const unsaved = unsavedRef.current();
            if (unsaved) await post('/keep', { content: unsaved, taken_by: result.by }).catch(() => null);
            setState({ status: 'taken', by: result.by });
        }
    }, [post]);

    // Take the lock when the page (or the language) opens, release it when it closes.
    React.useEffect(() => {
        if (!enabled || !base) return;
        setState({ status: 'starting' });
        void renew();
        const release = () => { void post('/release', {}, true).catch(() => null); };
        window.addEventListener('pagehide', release);
        return () => {
            window.removeEventListener('pagehide', release);
            if (stateRef.current.status === 'holding') release();
        };
    }, [enabled, base, locale, renew, post]);

    // Renew while holding (and learn about a takeover).
    React.useEffect(() => {
        if (!enabled || state.status !== 'holding') return;
        const timer = window.setInterval(() => { void renew(); }, RENEW_EVERY);
        return () => window.clearInterval(timer);
    }, [enabled, state.status, renew]);

    const takeOver = async () => {
        await post('/take').catch(() => toast.error('Could not take over. Try again.'));
        // Reload to edit the latest saved version.
        window.location.reload();
    };

    const loadKept = async (keptId: number) => fetchJson<{ content: Record<string, unknown> }>(adminUrl(`kept-edits/${keptId}`, adminPath)).then((r) => r.content);
    const discardKept = async (keptId: number) => {
        await fetch(adminUrl(`kept-edits/${keptId}`, adminPath), {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
        });
        setKept((k) => k.filter((e) => e.id !== keptId));
    };

    const since = state.status === 'locked' ? new Date(state.since * 1000).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) : '';
    const what = type === 'entry' ? 'entry' : type === 'term' ? 'term' : 'global set';
    const language = locale ? ` (${locale.toUpperCase()})` : '';

    const dialogs = (
        <>
            <AlertDialog open={state.status === 'locked'}>
                <AlertDialogContent>
                    {state.status === 'locked' && !state.confirming && (
                        <>
                            <AlertDialogHeader>
                                <AlertDialogTitle className="flex items-center gap-2"><Lock className="size-4" /> {state.by} is editing this {what}{language}</AlertDialogTitle>
                                <AlertDialogDescription>
                                    They have had it open since {since}. If you take over, their unsaved changes are kept for them to recover, and
                                    they are sent back to the list.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <Button variant="outline" onClick={() => router.visit(leaveTo)}>Go back</Button>
                                <Button variant="outline" onClick={() => setState({ status: 'viewing', by: state.by })}>View only</Button>
                                <Button onClick={() => setState({ ...state, confirming: true })}>Take over</Button>
                            </AlertDialogFooter>
                        </>
                    )}
                    {state.status === 'locked' && state.confirming && (
                        <>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Take over from {state.by}?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    {state.by} will be told you took over and can no longer save here. Anything they haven&apos;t saved is kept
                                    separately; you can load it if you need it.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <Button variant="outline" onClick={() => setState({ ...state, confirming: false })}>Cancel</Button>
                                <Button variant="destructive" onClick={takeOver}>Take over</Button>
                            </AlertDialogFooter>
                        </>
                    )}
                </AlertDialogContent>
            </AlertDialog>
            <AlertDialog open={state.status === 'taken'}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2"><UserRound className="size-4" /> {state.status === 'taken' ? state.by : 'Someone'} took over</AlertDialogTitle>
                        <AlertDialogDescription>
                            They are editing this {what}{language} now. Your unsaved changes were kept: they appear on this page, ready to load
                            again, once you or they open it.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <Button onClick={() => router.visit(leaveTo)}>Back to the list</Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );

    return {
        /** Someone else holds the lock (or it was taken): no saving here. */
        readOnly: state.status === 'locked' || state.status === 'viewing' || state.status === 'taken',
        viewingBy: state.status === 'viewing' ? state.by : null,
        /** True once taken over: the unsaved-changes prompt must not block leaving. */
        takenOver: state.status === 'taken',
        kept,
        loadKept,
        discardKept,
        dialogs,
    };
}

/** "Ana's unsaved changes were kept" notices, with Load / Discard. */
export function KeptEditsNotice({ kept, onLoad, onDiscard, disabled }: {
    kept: KeptEdit[];
    onLoad: (id: number) => void;
    onDiscard: (id: number) => void;
    disabled?: boolean;
}) {
    if (kept.length === 0) return null;
    return (
        <div className="flex flex-col gap-2">
            {kept.map((k) => (
                <div key={k.id} className="flex flex-wrap items-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm">
                    <History className="size-4 text-muted-foreground" />
                    <span className="flex-1">
                        Unsaved changes of <strong>{k.by ?? 'another editor'}</strong>
                        {k.taken_by ? `, kept when ${k.taken_by} took over` : ''}
                        {k.at ? ` (${new Date(k.at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })})` : ''}.
                    </span>
                    <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={() => onLoad(k.id)}>Load into the form</Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => onDiscard(k.id)}>Discard</Button>
                </div>
            ))}
        </div>
    );
}

/** Shown when a save was refused because someone else saved meanwhile. */
export function VersionConflict({ message, onOverwrite }: { message?: string; onOverwrite: () => void }) {
    if (!message) return null;
    return (
        <div role="alert" className="flex flex-wrap items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
            <span className="flex-1">{message}</span>
            <Button type="button" size="sm" variant="outline" onClick={() => window.location.reload()}>Reload</Button>
            <Button type="button" size="sm" variant="destructive" onClick={onOverwrite}>Overwrite with mine</Button>
        </div>
    );
}
