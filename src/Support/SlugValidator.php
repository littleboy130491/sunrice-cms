<?php

declare(strict_types=1);

namespace Sunrice\Support;

use Illuminate\Support\Str;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\TermTranslation;

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
        $firstSegment = explode('/', $adminPath)[0];

        return $slug !== $firstSegment;
    }

    public static function fromTitle(string $title): string
    {
        return Str::slug($title);
    }

    /**
     * First free variant of a slug for an entry (appends -2, -3…).
     */
    public static function unique(string $slug, int $collectionId, string $locale, ?int $ignoreEntryId = null): string
    {
        $candidate = $slug === '' ? 'entry' : $slug;
        $i = 2;
        while (! static::isUniqueForEntry($candidate, $collectionId, $locale, $ignoreEntryId)) {
            $candidate = "{$slug}-{$i}";
            $i++;
        }

        return $candidate;
    }

    /**
     * First free variant of a slug for a term.
     */
    public static function uniqueForTerm(string $slug, int $taxonomyId, string $locale, ?int $ignoreTermId = null): string
    {
        $candidate = $slug === '' ? 'term' : $slug;
        $i = 2;
        while (! static::isUniqueForTerm($candidate, $taxonomyId, $locale, $ignoreTermId)) {
            $candidate = "{$slug}-{$i}";
            $i++;
        }

        return $candidate;
    }

    /**
     * Slug uniqueness is per (collection_id, locale) and includes
     * trashed entries, so restoring one never conflicts.
     */
    public static function isUniqueForEntry(string $slug, int $collectionId, string $locale, ?int $ignoreEntryId = null): bool
    {
        $query = EntryTranslation::query()
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
        $query = TermTranslation::query()
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
