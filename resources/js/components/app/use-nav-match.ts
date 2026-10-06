import { usePage } from '@inertiajs/react';
import { adminUrl } from '@/lib/route';
import type { NavGroup, NavItem, SharedProps } from '@/types';

export interface NavMatch {
    group: NavGroup;
    item: NavItem;
}

/**
 * The navigation item whose URL is the longest prefix of the current page,
 * used for the active sidebar state, breadcrumbs and the document title.
 */
export function useNavMatch(): NavMatch | null {
    const { navigation, adminPath } = usePage<SharedProps>().props;
    const path = usePage().url.split('?')[0];

    let best: NavMatch | null = null;
    let bestLength = 0;
    for (const group of navigation ?? []) {
        for (const item of group.items) {
            const href = adminUrl(item.href, adminPath);
            if ((path === href || path.startsWith(`${href}/`)) && href.length > bestLength) {
                best = { group, item };
                bestLength = href.length;
            }
        }
    }

    return best;
}
