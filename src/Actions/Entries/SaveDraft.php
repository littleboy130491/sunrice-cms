<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Illuminate\Validation\ValidationException;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Support\SlugValidator;

/**
 * Writes {title, slug, data, seo} into the translation's `draft`
 * column only — the public site never reads `draft`, so saving a
 * draft on a published entry leaves the live version untouched.
 */
class SaveDraft
{
    /**
     * @param  array{title: string, slug?: string, data?: array, seo?: array}  $attributes
     *
     * @throws ValidationException
     */
    public function handle(EntryTranslation $translation, array $attributes): EntryTranslation
    {
        $entry = $translation->entry;
        $blueprint = $entry->activeBlueprint();
        $schema = $blueprint?->schema();

        $slug = (string) ($attributes['slug'] ?? '');
        if ($slug === '') {
            $slug = SlugValidator::fromTitle((string) ($attributes['title'] ?? ''));
        }

        $data = $attributes['data'] ?? [];
        $seo = $attributes['seo'] ?? [];

        // Validate against the blueprint (with a tolerant prefix so
        // rules apply to the nested `data` array).
        if ($schema !== null) {
            validator(
                ['data' => $data],
                $schema->rules('data'),
            )->validate();

            $data = $schema->normalize($data);
        }

        $translation->draft = [
            'title' => (string) ($attributes['title'] ?? $translation->title),
            'slug' => $slug,
            'data' => $data,
            'seo' => $seo,
        ];
        $translation->save();

        return $translation;
    }
}
