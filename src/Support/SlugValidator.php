<?php

declare(strict_types=1);

namespace Sunrice\Support;

use Illuminate\Support\Str;

class SlugValidator
{
    /**
     * Kebab-case format, not a locale code, not the admin path's first
     * segment. Uniqueness is checked separately (it needs the record).
     */
    public static function isValid(string $slug): bool
    {
        if ($slug === '' || ! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
            return false;
        }

        if (in_array($slug, Locales::available(), true)) {
            return false;
        }

        $adminPath = trim((string) config('sunrice.admin.path', 'cms'), '/');
        $firstSegment = explode('/', $adminPath)[0] ?? '';

        return $slug !== $firstSegment;
    }

    public static function fromTitle(string $title): string
    {
        return Str::slug($title);
    }

    /**
     * Slug uniqueness is per (collection_id, locale) and includes
     * trashed entries, so restoring one never conflicts.
     */
    public static function isUniqueForEntry(string $slug, int $collectionId, string $locale, ?int $ignoreEntryId = null): bool
    {
        $query = \Sunrice\Models\EntryTranslation::query()
            ->where('collection_id', $collectionId)
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('entry', fn ($q) => $q->withTrashed());

        if ($ignoreEntryId !== null) {
            $query->where('entry_id', '!=', $ignoreEntryId);
        }

        return ! $query->exists();
    }

    /**
     * Term slugs are unique per (taxonomy_id, locale), trashed included.
     */
    public static function isUniqueForTerm(string $slug, int $taxonomyId, string $locale, ?int $ignoreTermId = null): bool
    {
        $query = \Sunrice\Models\TermTranslation::query()
            ->where('taxonomy_id', $taxonomyId)
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('term', fn ($q) => $q->withTrashed());

        if ($ignoreTermId !== null) {
            $query->where('term_id', '!=', $ignoreTermId);
        }

        return ! $query->exists();
    }
}
