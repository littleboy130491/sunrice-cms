<?php

declare(strict_types=1);

namespace Sunrice\Actions\Collections;

use Illuminate\Validation\ValidationException;
use Sunrice\Events\ContentChanged;
use Sunrice\Fields\TranslationOverlay;
use Sunrice\Models\Collection;
use Sunrice\Support\Locales;

/**
 * Saves a collection's listing page (archive) content for one language:
 * heading, intro and the listing blueprint's fields, stored in
 * archive_data as {locale: {title, intro, data}}. A secondary language
 * keeps only its translatable values; the rest come from the main one.
 */
class SaveListing
{
    /**
     * @param  array<string, mixed>  $attributes  locale, title, intro, data
     */
    public function handle(Collection $collection, array $attributes): Collection
    {
        $schema = $collection->archiveSchema();

        $validated = validator($attributes, [
            'locale' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
            'intro' => ['nullable', 'string', 'max:2000'],
            'data' => ['nullable', 'array'],
            'seo' => ['nullable', 'array'],
            'seo.title' => ['nullable', 'string', 'max:255'],
            'seo.description' => ['nullable', 'string', 'max:500'],
            'seo.canonical' => ['nullable', 'string', 'max:500'],
            'seo.image' => ['nullable', 'integer'],
            'seo.noindex' => ['nullable', 'boolean'],
        ] + ($schema === null ? [] : $schema->rules('data')))->validate();

        $locale = (string) $validated['locale'];
        if (! Locales::isAvailable($locale)) {
            throw ValidationException::withMessages(['locale' => 'Unknown language. Reload the page and try again.']);
        }

        $byLocale = Collection::archiveByLocale($collection->archive_data);
        $data = (array) ($validated['data'] ?? []);
        if ($schema !== null) {
            $data = $schema->normalize($data);
            if (! Locales::isMain($locale)) {
                $main = (array) ($byLocale[Locales::main()]['data'] ?? []);
                $data = TranslationOverlay::extract($schema->fields(), $data, $main);
            }
        }

        $byLocale[$locale] = array_filter([
            'title' => ($validated['title'] ?? null) ?: null,
            'intro' => ($validated['intro'] ?? null) ?: null,
            'data' => $data === [] ? null : $data,
            'seo' => array_filter((array) ($validated['seo'] ?? []), fn ($v) => $v !== null && $v !== '' && $v !== false) ?: null,
        ], fn ($value) => $value !== null);

        $collection->archive_data = array_filter($byLocale);
        $collection->save();

        ContentChanged::dispatch('collection_saved');

        return $collection;
    }
}
