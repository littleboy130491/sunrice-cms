import * as React from 'react';
import { router } from '@inertiajs/react';

/**
 * Guards a form with unsaved edits: asks before leaving the page (links,
 * back button, closing the tab) and saves on Ctrl/Cmd+S.
 */
export function useUnsavedChanges(dirty: boolean, onSave?: () => void): void {
    const dirtyRef = React.useRef(dirty);
    dirtyRef.current = dirty;
    const saveRef = React.useRef(onSave);
    saveRef.current = onSave;

    React.useEffect(() => {
        const beforeUnload = (e: BeforeUnloadEvent) => {
            if (!dirtyRef.current) return;
            e.preventDefault();
            e.returnValue = '';
        };
        // Only page changes (GET visits) leave the form; saves and publishes
        // are posts. Prefetches (hovering a sidebar link) and partial
        // reloads of this page don't leave it either, and a visit already
        // confirmed isn't asked about again.
        let confirmed = false;
        const removeBefore = router.on('before', (event) => {
            const visit = event.detail.visit;
            if (!dirtyRef.current || confirmed || visit.method !== 'get' || visit.prefetch || visit.only.length > 0) {
                return;
            }
            if (window.confirm('You have unsaved changes. Leave without saving?')) {
                confirmed = true;
            } else {
                event.preventDefault();
            }
        });
        // A confirmed visit that didn't leave (cancelled, failed): ask again next time.
        const removeFinish = router.on('finish', () => {
            confirmed = false;
        });
        const keydown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's' && saveRef.current) {
                e.preventDefault();
                saveRef.current();
            }
        };
        window.addEventListener('beforeunload', beforeUnload);
        window.addEventListener('keydown', keydown);

        return () => {
            removeBefore();
            removeFinish();
            window.removeEventListener('beforeunload', beforeUnload);
            window.removeEventListener('keydown', keydown);
        };
    }, []);
}
