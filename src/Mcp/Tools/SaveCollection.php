<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Structure\SaveCollection as SaveCollectionAction;
use Sunrice\Mcp\Presenter;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Taxonomy;

#[Description('Create a collection (content type such as pages, articles, products) or update one, found by handle. Settings keys: route (entry URL: "blog" → /blog/{slug}, "/" for the site root, or a pattern like "/news/{slug}"), has_single (entries have pages, default true), hierarchical (parent pages, URLs nest), has_archive + archive_route + per_page (listing page; its content: get_listing / save_listing), translatable, template / archive_template (Blade views), sort (published_at|title|sort_order|created_at|updated_at) + sort_direction, icon (lucide name), seo {description, image, noindex, title_field, description_field, image_field} (merged key by key; the *_field keys pick the entry field that fills an empty meta title, description or share image: a field handle, "none", or "" for automatic). Settings are merged into the current ones. Create the blueprint first (save_blueprint) and pass its handle; archive_blueprint is the same for the listing page\'s own fields.')]
class SaveCollection extends SunriceTool
{
    protected string $name = 'save_collection';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'handle' => ['required', 'string'],
            'title' => ['nullable', 'string'],
            'blueprint' => ['nullable', 'string'],
            'archive_blueprint' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'taxonomies' => ['nullable', 'array'],
            'taxonomies.*' => ['string'],
        ]);
        $collection = Collection::query()->where('handle', $args['handle'])->first();
        $this->authorize($collection === null ? 'create' : 'update', $collection ?? Collection::class);

        $attributes = ['handle' => $args['handle'], 'title' => $args['title'] ?? ($collection === null ? ucfirst($args['handle']) : $collection->title)];
        if (array_key_exists('blueprint', $args)) {
            $blueprint = $args['blueprint'] ? $this->findByHandle(Blueprint::class, $args['blueprint']) : null;
            if ($args['blueprint'] && $blueprint === null) {
                return $this->notFound('Blueprint');
            }
            $attributes['blueprint_id'] = $blueprint?->id;
        } elseif ($collection !== null) {
            $attributes['blueprint_id'] = $collection->blueprint_id;
        }
        if (isset($args['settings'])) {
            $attributes['settings'] = $args['settings'];
            if (is_array($args['settings']['seo'] ?? null) && $collection !== null) {
                $attributes['settings']['seo'] = static::mergeSettings(['seo' => (array) $collection->setting('seo', [])], ['seo' => $args['settings']['seo']])['seo'];
            }
        }
        if (array_key_exists('archive_blueprint', $args)) {
            $listing = $args['archive_blueprint'] ? $this->findByHandle(Blueprint::class, $args['archive_blueprint']) : null;
            if ($args['archive_blueprint'] && $listing === null) {
                return $this->notFound('Listing blueprint');
            }
            $attributes['settings'] = array_merge((array) ($attributes['settings'] ?? []), ['archive_blueprint_id' => $listing?->id]);
        }
        if (isset($args['taxonomies'])) {
            $attributes['taxonomy_ids'] = Taxonomy::query()->whereIn('handle', $args['taxonomies'])->pluck('id')->all();
        }

        $saved = app(SaveCollectionAction::class)->handle($attributes, $collection);

        return $this->json(['saved' => true, 'created' => $collection === null] + Presenter::collection($saved->fresh(['blueprint', 'taxonomies']) ?? $saved));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'handle' => $schema->string()->description('Lowercase handle (a-z, 0-9, _). An existing handle updates that collection.')->required(),
            'title' => $schema->string(),
            'blueprint' => $schema->string()->description('Blueprint handle for the entries\' fields.'),
            'archive_blueprint' => $schema->string()->description('Blueprint handle for the listing page\'s own fields (empty string removes it).'),
            'settings' => $schema->object()->description('See the tool description; merged into the current settings.'),
            'taxonomies' => $schema->array()->items($schema->string())->description('Taxonomy handles attached to the collection (replaces the list).'),
        ];
    }
}
