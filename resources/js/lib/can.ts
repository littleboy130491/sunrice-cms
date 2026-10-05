import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

export function usePermissions(): string[] {
    const permissions = usePage<SharedProps>().props.permissions ?? [];

    return permissions as string[];
}

export function useCan() {
    const permissions = usePermissions();
    const wildcard = permissions.includes('*');

    return (permission: string): boolean => wildcard || permissions.includes(permission);
}
