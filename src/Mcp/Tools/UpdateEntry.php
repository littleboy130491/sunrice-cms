<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Entries\EnsureTranslation;
use Sunrice\Actions\Entries\PublishTranslation;
use Sunrice\Actions\Entries\SaveDraft;
use Sunrice\Actions\Entries\SetEntryParent;
use Sunrice\Actions\Entries\SyncEntryTerms;
use Sunrice\Mcp\Presenter;
use Sunrice\Models\Entry;
use Sunrice\Rules\ValidSlug;
use Sunrice\Support\Locales;

#[Description('Edit an entry in one language (default: the main language). Only the keys you send change: `data` and `seo` are merged into the current draft by field handle. Changes are saved as a draft; set publish: true to put them live (for another language, publishing marks that translation Ready). Use a language code in `locale` to write a translation; it is created when missing. Terms, parent and template belong to the entry (all languages) and apply at once.')]
class UpdateEntry extends SunriceTool
{
    protected string $name = 'update_entry';

    public function handle(Request $request): Response
    {
        $entry = Entry::query()->find((int) $request->get('id'));
        if ($entry === null) {
            return $this->notFound('Entry');
        }
        $locale = (string) ($request->get('locale') ?: Locales::main());
        $this->authorize(Locales::isMain($locale) ? 'update' : 'translate', $entry);

        $existing = $entry->translations()->where('locale', $locale)->first();
        $args = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                new ValidSlug([$existing?->slug, $existing?->draft['slug'] ?? null]),
                Rule::unique('sunrice_entry_translations', 'slug')
                    ->where(fn ($q) => $q->where('collection_id', $entry->collection_id)->where('locale', $locale))
                    ->ignore($entry->id, 'entry_id'),
            ],
            'data' => ['nullable', 'array'],
            'seo' => ['nullable', 'array'],
            'term_ids' => ['nullable', 'array'],
            'term_ids.*' => ['integer'],
            'parent_id' => ['nullable', 'integer'],
            'template' => ['nullable', 'string', 'max:150', 'regex:/^[A-Za-z0-9_.:\/-]*$/'],
            'publish' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
        $all = $request->all();
        $editor = Gate::allows('update', $entry);

        // Entry-wide settings: editors only.
        if ($editor) {
            if (array_key_exists('term_ids', $all)) {
                app(SyncEntryTerms::class)->handle($entry, (array) ($args['term_ids'] ?? []));
            }
            if (array_key_exists('parent_id', $all)) {
                app(SetEntryParent::class)->handle($entry, isset($args['parent_id']) ? (int) $args['parent_id'] : null);
            }
            if (array_key_exists('template', $all)) {
                $entry->update(['template' => ($args['template'] ?? '') ?: null]);
            }
        }

        $translation = EnsureTranslation::for($entry, $locale);
        $touchesContent = array_intersect(['title', 'slug', 'data', 'seo'], array_keys($all)) !== [];
        if ($touchesContent) {
            $draft = $translation->draft;
            app(SaveDraft::class)->handle($translation, [
                'title' => $args['title'] ?? $draft['title'] ?? $translation->title,
                'slug' => $args['slug'] ?? $draft['slug'] ?? $translation->slug,
                'data' => array_replace(Presenter::draftData($entry, $translation), (array) ($args['data'] ?? [])),
                'seo' => array_replace((array) ($draft['seo'] ?? $translation->seo ?? []), (array) ($args['seo'] ?? [])),
            ]);
        }

        if (! empty($args['publish'])) {
            $this->authorize('publish', $entry);
            app(PublishTranslation::class)->handle($translation->fresh() ?? $translation, Locales::isMain($locale) ? ($args['published_at'] ?? null) : null);
        }

        return $this->json(['saved' => true, 'published' => ! empty($args['publish'])] + Presenter::entry($entry->fresh() ?? $entry));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Entry id.')->required(),
            'locale' => $schema->string()->description('Language code (default: the main language).'),
            'title' => $schema->string(),
            'slug' => $schema->string(),
            'data' => $schema->object()->description('Field values to change, by handle (merged into the draft).'),
            'seo' => $schema->object()->description('{title, description, image (asset id), canonical, noindex} to change.'),
            'term_ids' => $schema->array()->items($schema->integer())->description('Replaces the entry\'s terms.'),
            'parent_id' => $schema->integer()->nullable()->description('Parent entry, or null for the top level (hierarchical collections).'),
            'template' => $schema->string()->description('Blade view for this entry; empty to use the collection\'s.'),
            'publish' => $schema->boolean()->description('Publish the result (default: keep as draft).'),
            'published_at' => $schema->string()->description('ISO date when publishing the main language; future = scheduled.'),
        ];
    }
}
