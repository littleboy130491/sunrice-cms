import { TriangleAlert } from 'lucide-react';

type Kind = 'collection' | 'taxonomy' | 'blueprint' | 'fieldset';

/** What each kind of handle is wired to, so the warning names the real risks. */
const RISKS: Record<Kind, (h: string) => string[]> = {
    collection: (h) => [
        `Template files named after it (resources/views/sunrice/${h}/…) stop being used.`,
        `Code calling it by name (<x-sunrice::entries collection="${h}">, sunrice_entries('${h}'), EntryQuery) stops finding entries.`,
        `Default URLs (/${h}/…) change, so links and search results pointing at the old ones break, unless the collection has its own route.`,
    ],
    taxonomy: (h) => [
        `Template files named after it (resources/views/sunrice/taxonomies/${h}…) stop being used.`,
        `Code filtering by it (whereTerm('${h}', …)) finds nothing.`,
        'Term page URLs built from it change, so links and search results to the old ones break.',
    ],
    blueprint: (h) => [
        `Code looking it up by handle (Blueprint::where('handle', '${h}')) stops finding it.`,
        'Collections, taxonomies and globals keep using it: they point at it by id, not handle.',
    ],
    fieldset: (h) => [
        `Blueprints that import the "${h}" fieldset by handle lose those fields until they're updated.`,
    ],
};

/**
 * Shown on edit forms once the handle differs from the saved one: code,
 * templates and URLs refer to handles by name, and nothing updates them.
 */
export function HandleChangeWarning({ kind, original, current }: { kind: Kind; original: string | null | undefined; current: string }) {
    if (!original || current === original) return null;

    return (
        <div role="alert" className="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2.5 text-[13px] text-amber-900 dark:text-amber-200">
            <TriangleAlert className="mt-0.5 size-4 shrink-0" />
            <div className="grid gap-1.5">
                <p className="font-medium">Changing the handle can break your site. Not recommended unless you know what depends on it.</p>
                <p>
                    Templates, code and URLs refer to this {kind} as <code className="font-mono">{original}</code>, and nothing updates them for you. After the
                    change:
                </p>
                <ul className="ml-4 list-disc space-y-0.5">
                    {RISKS[kind](original).map((risk) => <li key={risk}>{risk}</li>)}
                </ul>
                <p>Only continue if you'll update those references to <code className="font-mono">{current || '…'}</code> too. To undo, put back <code className="font-mono">{original}</code> before saving.</p>
            </div>
        </div>
    );
}
