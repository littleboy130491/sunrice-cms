<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Support\Locales;
use Sunrice\Support\SlugValidator;

class CreateEntry
{
    /**
     * Creates a draft entry plus its main-language translation with
     * draft content populated.
     *
     * @param  array{title: string, slug?: string, data?: array, seo?: array}  $attributes
     */
    public function handle(Collection $collection, array $attributes, ?int $authorId = null, ?int $blueprintId = null): Entry
    {
        $blueprint = $blueprintId ? \Sunrice\Models\Blueprint::findOrFail($blueprintId) : $collection->blueprint;
        $schema = $blueprint?->schema();

        $data = $schema ? $schema->normalize($attributes['data'] ?? []) : ($attributes['data'] ?? []);

        $entry = Entry::create([
            'collection_id' => $collection->id,
            'blueprint_id' => $blueprintId,
            'author_id' => $authorId ?? auth(config('sunrice.auth.guard'))->id(),
            'status' => 'draft',
            'published_at' => null,
        ]);

        $title = (string) ($attributes['title'] ?? '');
        $slug = $attributes['slug'] ?? '';
        if ($slug === '') {
            $slug = SlugValidator::fromTitle($title);
        }

        $translation = new EntryTranslation([
            'collection_id' => $collection->id,
            'locale' => Locales::main(),
            'title' => $title,
            'slug' => $slug,
            'data' => $data,
            'seo' => $attributes['seo'] ?? [],
            'is_ready' => false,
        ]);
        $translation->draft = [
            'title' => $translation->title,
            'slug' => $translation->slug,
            'data' => $data,
            'seo' => $translation->seo,
        ];
        $entry->translations()->save($translation);

        return $entry->refresh();
    }
}
