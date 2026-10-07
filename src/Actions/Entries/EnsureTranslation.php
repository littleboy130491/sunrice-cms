<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Illuminate\Validation\ValidationException;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Support\Locales;
use Sunrice\Support\SlugValidator;

/**
 * An entry's translation in a language, created (from the main
 * language's title, slug and SEO) when it doesn't exist yet.
 */
class EnsureTranslation
{
    /**
     * @throws ValidationException
     */
    public static function for(Entry $entry, string $locale): EntryTranslation
    {
        if (! Locales::isAvailable($locale)) {
            throw ValidationException::withMessages(['locale' => 'Unknown language. Reload the page and try again.']);
        }

        $main = $entry->mainTranslation();

        return $entry->translations()->firstOrCreate(
            ['locale' => $locale],
            [
                'collection_id' => $entry->collection_id,
                'title' => $main === null ? '' : $main->title,
                'slug' => SlugValidator::unique($main === null ? 'entry' : $main->slug, $entry->collection_id, $locale),
                // Secondary languages store only translated values; until
                // the editor translates something, the main text shows.
                'data' => [],
                'seo' => $main === null ? [] : $main->seo,
            ],
        );
    }
}
