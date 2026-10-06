<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Support\SlugValidator;

/**
 * Copies an entry and all its translations as a new draft with
 * '-copy' slugs.
 */
class DuplicateEntry
{
    public function handle(Entry $entry): Entry
    {
        $copy = $entry->replicate(['status', 'published_at']);
        $copy->status = 'draft';
        $copy->published_at = null;
        $copy->push();

        foreach ($entry->translations()->get() as $translation) {
            $new = $translation->replicate();
            $new->entry_id = $copy->id;
            $new->slug = $this->copySlug($translation);
            $new->is_ready = false;
            $new->content_published_at = null;
            $new->save();
        }

        return $copy->refresh();
    }

    protected function copySlug(EntryTranslation $translation): string
    {
        $base = $translation->slug.'-copy';
        $slug = $base;
        $i = 2;
        while (! SlugValidator::isUniqueForEntry($slug, $translation->collection_id, $translation->locale)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
