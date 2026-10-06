import { useCallback, useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';

export interface TableQueryState {
    search: string;
    filters: Record<string, string>;
    sort: string;
    perPage: number;
}

/**
 * Syncs table state to the URL query string via Inertia GET requests
 * (preserveState + replace), so server-driven tables stay shareable.
 */
export function useTableQuery(initial: Partial<TableQueryState> = {}) {
    const [state, setState] = useState<TableQueryState>({
        search: initial.search ?? '',
        filters: initial.filters ?? {},
        sort: initial.sort ?? '',
        perPage: initial.perPage ?? 20,
    });

    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    const push = useCallback((next: TableQueryState) => {
        const params: Record<string, string | number | Record<string, string>> = { per_page: next.perPage };
        if (next.search) params.search = next.search;
        if (next.sort) params.sort = next.sort;
        if (Object.keys(next.filters).length) params.filters = next.filters;

        router.get(window.location.pathname, params as never, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, []);

    const update = useCallback(
        (patch: Partial<TableQueryState>, debounced = false) => {
            setState((prev) => {
                const next = { ...prev, ...patch };
                if (debounce.current) clearTimeout(debounce.current);
                if (debounced) {
                    debounce.current = setTimeout(() => push(next), 300);
                } else {
                    push(next);
                }
                return next;
            });
        },
        [push],
    );

    useEffect(() => () => {
        if (debounce.current) clearTimeout(debounce.current);
    }, []);

    return { state, update };
}
