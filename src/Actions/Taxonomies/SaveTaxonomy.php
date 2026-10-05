<?php

declare(strict_types=1);

namespace Sunrice\Actions\Taxonomies;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Taxonomy;
use Sunrice\Permissions\SyncPermissions;

class SaveTaxonomy
{
    /**
     * @param  array{handle?: string, title: string, blueprint_id?: ?int, hierarchical?: bool, settings?: array}  $attributes
     */
    /** @param array<string, mixed> $attributes */
    public function handle(array $attributes, ?Taxonomy $taxonomy = null): Taxonomy
    {
        $validated = validator($attributes, [
            'handle' => [
                $taxonomy === null ? 'required' : 'sometimes',
                'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('sunrice_taxonomies', 'handle')->ignore($taxonomy),
            ],
            'title' => ['required', 'string', 'max:255'],
            'blueprint_id' => ['nullable', 'integer', Rule::exists('sunrice_blueprints', 'id')],
            'hierarchical' => ['boolean'],
            'settings' => ['array'],
            'settings.sluggable' => ['boolean'],
            'settings.route' => ['nullable', 'string', 'max:255'],
            'settings.fallback' => ['nullable', Rule::in(['main', '404'])],
        ])->validate();

        $taxonomy ??= new Taxonomy;
        $taxonomy->fill([
            'handle' => $validated['handle'] ?? $taxonomy->handle,
            'title' => $validated['title'],
            'blueprint_id' => Arr::get($validated, 'blueprint_id', $taxonomy->blueprint_id),
            'hierarchical' => (bool) ($validated['hierarchical'] ?? $taxonomy->hierarchical ?? false),
            'settings' => Arr::get($validated, 'settings', $taxonomy->settings ?? []),
        ]);
        $taxonomy->save();

        app(SyncPermissions::class)->handle();
        ContentChanged::dispatch('taxonomy_saved');

        return $taxonomy->refresh();
    }
}
