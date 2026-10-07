<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Entries\CreateEntry as CreateEntryAction;
use Sunrice\Actions\Entries\PublishTranslation;
use Sunrice\Actions\Entries\SetEntryParent;
use Sunrice\Actions\Entries\SyncEntryTerms;
use Sunrice\Mcp\Presenter;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Entry;
use Sunrice\Rules\ValidSlug;
use Sunrice\Support\Locales;

#[Description('Create an entry in the main language, as a draft unless publish is true. `data` holds the blueprint field values by handle (see get_site_info / get_entry for fields): text as strings, rich_text as HTML, toggle as booleans, asset as an asset id, entries/terms as arrays of ids, link as {type: "url", url} or {type: "entry", entry_id}. Add other languages afterwards with update_entry.')]
class CreateEntry extends SunriceTool
{
    protected string $name = 'create_entry';

    public function handle(Request $request): Response
    {
        $collection = $this->collection($request->get('collection'));
        if ($collection === null) {
            return $this->notFound('Collection');
        }
        $this->authorize('create', [Entry::class, $collection->id]);

        $args = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', new ValidSlug,
                Rule::unique('sunrice_entry_translations', 'slug')
                    ->where(fn ($q) => $q->where('collection_id', $collection->id)->where('locale', Locales::main())),
            ],
            'data' => ['nullable', 'array'],
            'seo' => ['nullable', 'array'],
            'blueprint' => ['nullable', 'string'],
            'term_ids' => ['nullable', 'array'],
            'term_ids.*' => ['integer'],
            'parent_id' => ['nullable', 'integer'],
            'template' => ['nullable', 'string', 'max:150', 'regex:/^[A-Za-z0-9_.:\/-]+$/'],
            'publish' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
        $blueprintId = null;
        if (! empty($args['blueprint'])) {
            $blueprint = $this->findByHandle(Blueprint::class, $args['blueprint']);
            if ($blueprint === null) {
                return $this->notFound('Blueprint');
            }
            $blueprintId = $blueprint->id;
        }
        if (! empty($args['publish'])) {
            $this->authorize('sunrice.entries.'.$collection->id.'.publish');
        }

        $entry = DB::transaction(function () use ($collection, $args, $blueprintId, $request): Entry {
            $entry = app(CreateEntryAction::class)->handle($collection, [
                'title' => $args['title'],
                'slug' => $args['slug'] ?? '',
                'data' => $args['data'] ?? [],
                'seo' => $args['seo'] ?? [],
            ], $request->user()?->getAuthIdentifier(), $blueprintId);

            if (! empty($args['term_ids'])) {
                app(SyncEntryTerms::class)->handle($entry, $args['term_ids']);
            }
            if (! empty($args['parent_id'])) {
                app(SetEntryParent::class)->handle($entry, (int) $args['parent_id']);
            }
            if (! empty($args['template'])) {
                $entry->update(['template' => $args['template']]);
            }
            if (! empty($args['publish'])) {
                $translation = $entry->mainTranslation();
                if ($translation !== null) {
                    app(PublishTranslation::class)->handle($translation, $args['published_at'] ?? null);
                }
            }

            return $entry;
        });

        return $this->json(['created' => true] + Presenter::entry($entry->fresh() ?? $entry));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'collection' => $schema->string()->description('Collection handle (or id).')->required(),
            'title' => $schema->string()->required(),
            'slug' => $schema->string()->description('URL slug; made from the title when empty.'),
            'data' => $schema->object()->description('Field values by handle.'),
            'seo' => $schema->object()->description('{title, description, image (asset id), canonical, noindex}.'),
            'blueprint' => $schema->string()->description('Another blueprint handle than the collection\'s (rare).'),
            'term_ids' => $schema->array()->items($schema->integer())->description('Terms of the taxonomies attached to the collection.'),
            'parent_id' => $schema->integer()->description('Parent entry (hierarchical collections).'),
            'template' => $schema->string()->description('Blade view overriding the collection template.'),
            'publish' => $schema->boolean()->description('Publish right away (default: save as draft).'),
            'published_at' => $schema->string()->description('ISO date; a future date schedules the entry.'),
        ];
    }
}
