<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Taxonomies\SaveTaxonomy as SaveTaxonomyAction;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Taxonomy;

#[Description('Create or update a taxonomy (categories, tags…), found by handle. hierarchical allows parent terms. Settings: has_archive + route (term page URL, e.g. "/category/{slug}") + per_page, template (Blade view), sluggable, seo {description, image, noindex}. collections: handles of the collections whose entries use it.')]
class SaveTaxonomy extends SunriceTool
{
    protected string $name = 'save_taxonomy';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'handle' => ['required', 'string'],
            'title' => ['nullable', 'string'],
            'hierarchical' => ['nullable', 'boolean'],
            'blueprint' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'collections' => ['nullable', 'array'],
            'collections.*' => ['string'],
        ]);
        $taxonomy = Taxonomy::query()->where('handle', $args['handle'])->first();
        $this->authorize($taxonomy === null ? 'create' : 'update', $taxonomy ?? Taxonomy::class);

        $attributes = [
            'handle' => $args['handle'],
            'title' => $args['title'] ?? $taxonomy?->title ?? ucfirst($args['handle']),
            'hierarchical' => $args['hierarchical'] ?? (bool) ($taxonomy?->hierarchical ?? false),
            'blueprint_id' => $taxonomy?->blueprint_id,
            'settings' => array_merge($taxonomy === null ? [] : ($taxonomy->settings ?? []), $args['settings'] ?? []),
        ];
        if (! empty($args['blueprint'])) {
            $blueprint = $this->findByHandle(Blueprint::class, $args['blueprint']);
            if ($blueprint === null) {
                return $this->notFound('Blueprint');
            }
            $attributes['blueprint_id'] = $blueprint->id;
        }
        $attributes['collection_ids'] = isset($args['collections'])
            ? Collection::query()->whereIn('handle', $args['collections'])->pluck('id')->all()
            : ($taxonomy?->collections()->pluck('sunrice_collections.id')->all() ?? []);

        $saved = app(SaveTaxonomyAction::class)->handle($attributes, $taxonomy);

        return $this->json([
            'saved' => true,
            'created' => $taxonomy === null,
            'id' => $saved->id,
            'handle' => $saved->handle,
            'hierarchical' => (bool) $saved->hierarchical,
            'settings' => $saved->settings,
            'collections' => $saved->collections()->pluck('handle')->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'handle' => $schema->string()->required(),
            'title' => $schema->string(),
            'hierarchical' => $schema->boolean(),
            'blueprint' => $schema->string()->description('Blueprint handle for extra term fields.'),
            'settings' => $schema->object()->description('Merged into the current settings.'),
            'collections' => $schema->array()->items($schema->string())->description('Collection handles (replaces the list).'),
        ];
    }
}
