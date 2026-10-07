<?php

declare(strict_types=1);

namespace Sunrice\Actions\Taxonomies;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Collection;
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
            'settings.has_archive' => ['boolean'],
            'settings.route' => ['nullable', 'string', 'max:255', 'regex:#^[A-Za-z0-9/_{}.-]*$#'],
            'settings.per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'settings.titles' => ['nullable', 'array'],
            'settings.titles.*' => ['nullable', 'string', 'max:255'],
            'settings.seo' => ['nullable', 'array'],
            'settings.seo.description' => ['nullable', 'string', 'max:500'],
            'settings.seo.image' => ['nullable', 'integer'],
            'settings.seo.noindex' => ['nullable', 'boolean'],
            'settings.template' => ['nullable', 'string', 'max:150', 'regex:/^[A-Za-z0-9_.:\/-]+$/'],
            'collection_ids' => ['array'],
            'collection_ids.*' => ['integer', Rule::exists('sunrice_collections', 'id')],
        ])->validate();

        // Merge over the stored settings so keys the form doesn't edit survive.
        $settings = array_merge($taxonomy->settings ?? [], Arr::get($validated, 'settings', []));
        foreach (['per_page', 'template'] as $key) {
            if (array_key_exists($key, $settings) && ($settings[$key] === null || $settings[$key] === '')) {
                unset($settings[$key]);
            }
        }
        if (array_key_exists('titles', $settings)) {
            $settings['titles'] = Taxonomy::cleanTitles($settings['titles']);
            if ($settings['titles'] === []) {
                unset($settings['titles']);
            }
        }
        if (array_key_exists('seo', $settings)) {
            $settings['seo'] = array_filter((array) $settings['seo'], fn ($v) => $v !== null && $v !== '' && $v !== false);
            if ($settings['seo'] === []) {
                unset($settings['seo']);
            }
        }
        if (isset($settings['per_page'])) {
            $settings['per_page'] = (int) $settings['per_page'];
        }
        // Empty: per-collection pages at /{collection}/{taxonomy}/{slug}.
        $route = Collection::normalizeRoute($settings['route'] ?? null);
        if ($route === null) {
            unset($settings['route']);
        } else {
            $settings['route'] = $route;
        }

        $taxonomy ??= new Taxonomy;
        $taxonomy->fill([
            'handle' => $validated['handle'] ?? $taxonomy->handle,
            'title' => $validated['title'],
            'blueprint_id' => Arr::get($validated, 'blueprint_id', $taxonomy->blueprint_id),
            'hierarchical' => (bool) ($validated['hierarchical'] ?? $taxonomy->hierarchical ?? false),
            'settings' => $settings,
        ]);
        $taxonomy->save();

        if (array_key_exists('collection_ids', $validated)) {
            $taxonomy->collections()->sync($validated['collection_ids']);
        }

        app(SyncPermissions::class)->handle();
        ContentChanged::dispatch('taxonomy_saved');

        return $taxonomy->refresh();
    }
}
