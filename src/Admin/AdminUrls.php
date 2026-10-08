<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Sunrice\Models\Entry;
use Sunrice\Models\Term;

/**
 * Admin addresses of an entry's or term's editor, nested under its
 * collection or taxonomy: /cms/collections/pages/entries/63.
 */
class AdminUrls
{
    public static function entry(Entry $entry): string
    {
        $collection = $entry->collection;

        return $collection === null
            ? route('sunrice.admin.entries.legacy-edit', $entry)
            : route('sunrice.admin.entries.edit', ['collection' => $collection->handle, 'entry' => $entry->id]);
    }

    public static function term(Term $term): string
    {
        $taxonomy = $term->taxonomy;

        return $taxonomy === null
            ? route('sunrice.admin.terms.legacy-edit', $term)
            : route('sunrice.admin.terms.edit', ['taxonomy' => $taxonomy->handle, 'term' => $term->id]);
    }
}
