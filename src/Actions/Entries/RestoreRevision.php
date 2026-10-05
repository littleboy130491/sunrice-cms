<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Sunrice\Models\Revision;

/**
 * Copies a revision's content into the translation's draft — does not
 * touch the live columns.
 */
class RestoreRevision
{
    public function handle(Revision $revision): \Sunrice\Models\EntryTranslation
    {
        $translation = $revision->translation;
        $translation->draft = $revision->content;
        $translation->save();

        return $translation;
    }
}
