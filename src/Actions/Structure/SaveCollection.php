<?php

declare(strict_types=1);

namespace Sunrice\Actions\Structure;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Collection;

class SaveCollection
{
    /**
     * @param  array{handle?: string, title: string, blueprint_id?: int, settings?: array, taxonomy_ids?: array}  $attributes
     */
    public function handle(array $attributes, ?Collection $collection = null): Collection
    {
        $validated = validator($attributes, [
            'handle' => [
                $collection === null ? 'required' : 'sometimes',
                'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('sunrice_collections', 'handle')->ignore($collection),
            ],
            'title' => ['required', 'string', 'max:255'],
            'blueprint_id' => ['nullable', 'integer', Rule::exists('sunrice_blueprints', 'id')],
            'settings' => ['array'],
            'settings.dated' => ['boolean'],
            'settings.translatable' => ['boolean'],
            'settings.sluggable' => ['boolean'],
            'settings.archivable' => ['boolean'],
            'settings.route' => ['nullable', 'string', 'max:255'],
            'settings.archive_entries_in' => ['nullable', 'string', 'max:100'],
            'settings.fallback' => ['nullable', Rule::in(['main', '404'])],
            'taxonomy_ids' => ['array'],
            'taxonomy_ids.*' => ['integer', Rule::exists('sunrice_taxonomies', 'id')],
        ])->validate();

        $collection ??= new Collection;
        $collection->fill([
            'handle' => $validated['handle'] ?? $collection->handle,
            'title' => $validated['title'],
            'blueprint_id' => Arr::get($validated, 'blueprint_id', $collection->blueprint_id),
            'settings' => Arr::get($validated, 'settings', $collection->settings ?? []),
        ]);
        $collection->save();

        if (array_key_exists('taxonomy_ids', $validated)) {
            $collection->taxonomies()->sync($validated['taxonomy_ids']);
        }

        app(\Sunrice\Permissions\SyncPermissions::class)->handle();
        ContentChanged::dispatch('collection_saved');

        return $collection->refresh();
    }
}
