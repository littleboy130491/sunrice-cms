import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

export function useAdminPath(): string {
    return usePage<SharedProps>().props.adminPath;
}

/**
 * Prefix a relative admin path with the configured admin path.
 * `adminUrl('collections/pages')` → `/cms/collections/pages`.
 */
export function adminUrl(path = '', adminPath?: string): string {
    const base = (adminPath ?? 'cms').replace(/^\/+|\/+$/g, '');
    const clean = path.replace(/^\/+/, '');

    return `/${base}${clean ? `/${clean}` : ''}`;
}
