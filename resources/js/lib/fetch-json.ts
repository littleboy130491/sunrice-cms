/**
 * GET a JSON admin endpoint. Throws an Error with a readable message on
 * a non-2xx response, so callers can show it instead of failing silently.
 */
export async function fetchJson<T = unknown>(url: string, init?: RequestInit): Promise<T> {
    const res = await fetch(url, { ...init, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...init?.headers } });
    if (!res.ok) {
        const body = await res.json().catch(() => null);
        const message = body?.message
            || (res.status === 403 ? "You don't have permission to see this."
                : res.status === 419 || res.status === 401 ? 'Your session expired. Reload the page.'
                    : `Request failed (${res.status}).`);
        throw new Error(message);
    }
    return res.json() as Promise<T>;
}

/** The CSRF token Laravel sets in the XSRF-TOKEN cookie, for fetch() writes. */
export function xsrfToken(): string {
    return decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}
