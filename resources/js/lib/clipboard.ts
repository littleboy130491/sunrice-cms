/**
 * Copy text to the clipboard. The Clipboard API only exists on secure
 * pages (https or localhost); on a plain-http address such as a local
 * http://site.test it falls back to a hidden textarea and execCommand.
 */
export async function copyText(text: string): Promise<void> {
    if (window.isSecureContext && navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        return;
    }

    // Inside an open dialog, so its focus trap doesn't pull focus back.
    const host = document.activeElement?.closest('[role="dialog"]') ?? document.body;
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.top = '0';
    area.style.left = '0';
    area.style.opacity = '0';
    host.appendChild(area);
    const previous = document.activeElement as HTMLElement | null;
    area.focus();
    area.select();
    let copied = false;
    try {
        copied = document.execCommand('copy');
    } finally {
        area.remove();
        previous?.focus?.();
    }
    if (!copied) {
        throw new Error('Copy failed');
    }
}
