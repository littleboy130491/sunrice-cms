import type { AdminField } from '@/types';

/** Per-page SEO fields (entries, terms, listing pages); mirrors EntriesController::seoFields(). */
export const SEO_FIELDS: AdminField[] = [
    { handle: 'title', type: 'text', label: 'Meta title' },
    { handle: 'description', type: 'textarea', label: 'Meta description' },
    { handle: 'canonical', type: 'text', label: 'Canonical URL' },
    { handle: 'image', type: 'asset', label: 'Open Graph image' },
    { handle: 'noindex', type: 'toggle', label: 'Hide from search engines (noindex)' },
];

/** Defaults a collection or taxonomy gives its pages. */
export const SEO_DEFAULT_FIELDS: AdminField[] = [
    { handle: 'description', type: 'textarea', label: 'Default meta description', instructions: 'Used by pages that leave their own description empty.' },
    { handle: 'image', type: 'asset', label: 'Default share image', instructions: 'Used by pages without their own Open Graph image.' },
    { handle: 'noindex', type: 'toggle', label: 'Hide all of these pages from search engines (noindex)' },
];

/** A collection's or taxonomy's settings.seo. */
export interface SeoDefaults {
    description?: string | null;
    image?: number | null;
    noindex?: boolean;
}
