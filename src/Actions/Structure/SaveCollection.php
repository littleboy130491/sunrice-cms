<?php

declare(strict_types=1);

namespace Sunrice\Actions\Structure;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Collection;
use Sunrice\Permissions\SyncPermissions;

class SaveCollection
{
    /**
     * @param  array{handle?: string, title: string, blueprint_id?: int, settings?: array, taxonomy_ids?: array}  $attributes
     */
    /** @param array<string, mixed> $attributes */
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
            'settings.has_single' => ['boolean'],
            'settings.sort' => ['nullable', Rule::in(array_keys(Collection::SORTS))],
            'settings.sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            // Fields for the listing page, edited from the collection's entries list.
            'settings.archive_blueprint_id' => ['nullable', 'integer', Rule::exists('sunrice_blueprints', 'id')],
            'settings.titles' => ['nullable', 'array'],
            'settings.titles.*' => ['nullable', 'string', 'max:255'],
            'settings.has_archive' => ['boolean'],
            'settings.route' => ['nullable', 'string', 'max:255', 'regex:#^[A-Za-z0-9/_{}.-]*$#'],
            'settings.archive_route' => ['nullable', 'string', 'max:255', 'regex:#^[A-Za-z0-9/_.-]*$#'],
            'settings.per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'settings.template' => ['nullable', 'string', 'max:150', 'regex:/^[A-Za-z0-9_.:\/-]+$/'],
            'settings.archive_template' => ['nullable', 'string', 'max:150', 'regex:/^[A-Za-z0-9_.:\/-]+$/'],
            'settings.icon' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/'],
            'settings.archive_entries_in' => ['nullable', 'string', 'max:100'],
            'taxonomy_ids' => ['array'],
            'taxonomy_ids.*' => ['integer', Rule::exists('sunrice_taxonomies', 'id')],
        ], [], [
            'settings.route' => 'route prefix',
            'settings.archive_route' => 'archive URL',
            'settings.per_page' => 'entries per page',
            'settings.template' => 'template',
            'settings.archive_template' => 'archive template',
        ])->validate();

        // Merge over the stored settings so keys the form doesn't edit survive.
        $settings = $this->cleanSettings(array_merge($collection->settings ?? [], Arr::get($validated, 'settings', [])));
        $handle = $validated['handle'] ?? $collection?->handle;
        $settings = $this->normalizeRoute($settings, (string) $handle);
        $this->ensureUniqueRoute($settings, (string) $handle, $collection);

        $collection ??= new Collection;
        $collection->fill([
            'handle' => $validated['handle'] ?? $collection->handle,
            'title' => $validated['title'],
            'blueprint_id' => Arr::get($validated, 'blueprint_id', $collection->blueprint_id),
            'settings' => $settings,
        ]);
        $collection->save();

        if (array_key_exists('taxonomy_ids', $validated)) {
            $collection->taxonomies()->sync($validated['taxonomy_ids']);
        }

        app(SyncPermissions::class)->handle();
        ContentChanged::dispatch('collection_saved');

        return $collection->refresh();
    }

    /**
     * Store the route as a URL pattern ('blog' → '/blog/{slug}'). A route
     * equal to the handle default is dropped, so it keeps following the
     * handle if that changes.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function normalizeRoute(array $settings, string $handle): array
    {
        $route = Collection::normalizeRoute($settings['route'] ?? null);

        if ($route === null || $route === '/'.$handle.'/{slug}') {
            unset($settings['route']);
        } else {
            $settings['route'] = $route;
        }

        return $settings;
    }

    /**
     * Drop blanks (so defaults apply) and tidy the archive URL.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function cleanSettings(array $settings): array
    {
        if (array_key_exists('titles', $settings)) {
            $settings['titles'] = Collection::cleanTitles($settings['titles']);
            if ($settings['titles'] === []) {
                unset($settings['titles']);
            }
        }
        foreach (['archive_route', 'template', 'archive_template', 'per_page', 'archive_blueprint_id', 'sort', 'sort_direction'] as $key) {
            if (array_key_exists($key, $settings) && ($settings[$key] === null || $settings[$key] === '')) {
                unset($settings[$key]);
            }
        }
        if (isset($settings['archive_route'])) {
            $settings['archive_route'] = '/'.trim((string) $settings['archive_route'], '/');
        }
        foreach (['per_page', 'archive_blueprint_id'] as $key) {
            if (isset($settings[$key])) {
                $settings[$key] = (int) $settings[$key];
            }
        }

        return $settings;
    }

    /**
     * Two collections can't serve entries from the same URL pattern.
     *
     * @param  array<string, mixed>  $settings
     *
     * @throws ValidationException
     */
    protected function ensureUniqueRoute(array $settings, string $handle, ?Collection $collection): void
    {
        if (($settings['has_single'] ?? true) === false) {
            return;
        }

        $route = (new Collection(['handle' => $handle, 'settings' => $settings]))->entryRoute();

        $taken = Collection::query()
            ->when($collection !== null, fn ($q) => $q->whereKeyNot($collection->getKey()))
            ->get()
            ->first(fn (Collection $other) => $other->setting('has_single', true) !== false && $other->entryRoute() === $route);

        if ($taken !== null) {
            throw ValidationException::withMessages([
                'settings.route' => "The URL {$route} is already used by the \"{$taken->title}\" collection. Choose another route prefix.",
            ]);
        }
    }
}
