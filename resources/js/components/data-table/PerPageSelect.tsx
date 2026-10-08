import { router, usePage } from '@inertiajs/react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { adminUrl } from '@/lib/route';
import { xsrfToken } from '@/lib/fetch-json';
import type { SharedProps } from '@/types';

export const PER_PAGE_OPTIONS = [10, 20, 25, 50, 100];

/** Remember the user's rows-per-page choice for a table (server-side, per user). */
export function savePerPage(adminPath: string, tableKey: string, perPage: number): Promise<unknown> {
    return fetch(adminUrl(`table-preferences/${tableKey}`, adminPath), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
        body: JSON.stringify({ per_page: perPage }),
    }).catch(() => undefined);
}

/** Reload the current page with another page size, back on page 1. */
function reloadWith(perPage: number) {
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    params.set('per_page', String(perPage));
    router.get(`${window.location.pathname}?${params.toString()}`, {}, { preserveState: true, preserveScroll: true, replace: true });
}

/**
 * "Rows per page" picker for a list footer. The choice is saved for the
 * user and table, so the list opens with it next time.
 */
export function PerPageSelect({ tableKey, value, options = PER_PAGE_OPTIONS, label = 'Rows per page', onChange }: {
    tableKey: string;
    value: number;
    options?: number[];
    label?: string;
    /** Instead of the default reload (e.g. a table that tracks its own query state). */
    onChange?: (perPage: number) => void;
}) {
    const { adminPath } = usePage<SharedProps>().props;
    const choices = [...new Set([...options, value])].sort((a, b) => a - b);

    const change = async (raw: string) => {
        const perPage = Number(raw);
        if (!perPage || perPage === value) return;
        await savePerPage(adminPath, tableKey, perPage);
        if (onChange) onChange(perPage);
        else reloadWith(perPage);
    };

    return (
        <label className="flex items-center gap-2 whitespace-nowrap">
            <span className="hidden sm:inline">{label}</span>
            <Select value={String(value)} onValueChange={change}>
                <SelectTrigger size="sm" className="w-[4.5rem]" aria-label={label}><SelectValue /></SelectTrigger>
                <SelectContent>
                    {choices.map((n) => <SelectItem key={n} value={String(n)}>{n}</SelectItem>)}
                </SelectContent>
            </Select>
        </label>
    );
}
