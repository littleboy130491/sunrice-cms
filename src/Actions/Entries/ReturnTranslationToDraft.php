<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use DomainException;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\EntryTranslation;
use Sunrice\Support\Locales;

/**
 * Marks a non-main translation as not-Ready: the public site falls
 * back to the whole main-language entry again.
 */
class ReturnTranslationToDraft
{
    public function handle(EntryTranslation $translation): EntryTranslation
    {
        if (Locales::isMain($translation->locale)) {
            throw new DomainException('The main-language translation cannot be returned to draft; unpublish the entry instead.');
        }

        $translation->is_ready = false;
        $translation->save();

        ContentChanged::dispatch('translation_drafted');

        return $translation;
    }
}
