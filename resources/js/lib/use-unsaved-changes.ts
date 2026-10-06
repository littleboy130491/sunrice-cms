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
        // Only page changes (GET visits) leave the form; saves and publishes are posts.
        const removeBefore = router.on('before', (event) => {
            if (dirtyRef.current && event.detail.visit.method === 'get' && !window.confirm('You have unsaved changes. Leave without saving?')) {
                event.preventDefault();
            }
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
            window.removeEventListener('beforeunload', beforeUnload);
            window.removeEventListener('keydown', keydown);
        };
    }, []);
}
