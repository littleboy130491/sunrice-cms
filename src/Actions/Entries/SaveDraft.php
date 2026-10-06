<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Illuminate\Validation\ValidationException;
use Sunrice\Fields\TranslationOverlay;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Support\Locales;
use Sunrice\Support\SlugValidator;

/**
 * Writes {title, slug, data, seo} into the translation's `draft`
 * column only — the public site never reads `draft`, so saving a
 * draft on a published entry leaves the live version untouched.
 *
 * A secondary language receives the full editor data but stores only
 * its translated values (TranslationOverlay); the layout and every
 * non-translatable value stay owned by the main language.
 */
class SaveDraft
{
    /**
     * @param  array{title?: string, slug?: string, data?: array<string, mixed>, seo?: array<string, mixed>, is_ready?: bool}  $attributes
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

            if (! Locales::isMain($translation->locale)) {
                $main = $entry->mainTranslation();
                $data = TranslationOverlay::extract(
                    $schema->fields(),
                    $data,
                    (array) ($main->draft['data'] ?? $main->data ?? []),
                );
            }
        }

        $translation->draft = [
            'title' => (string) ($attributes['title'] ?? $translation->title),
            'slug' => $slug,
            'data' => $data,
            'seo' => $seo,
        ];
        if (array_key_exists('is_ready', $attributes)) {
            $translation->is_ready = (bool) $attributes['is_ready'];
        }
        $translation->save();

        return $translation;
    }
}
