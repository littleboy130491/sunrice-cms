<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Taxonomies\RestoreTerm;
use Sunrice\Actions\Taxonomies\SaveTerm as SaveTermAction;
use Sunrice\Actions\Taxonomies\TrashTerm;
use Sunrice\Mcp\Presenter;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;

#[Description('Create a term (no id) or update one (id), or trash / restore one (action). translations: {locale: {name, slug, data, seo}}; the main language is required when creating, other languages are optional and a language you leave out is kept as is. parent_id for hierarchical taxonomies. Trashing a term also trashes its child terms.')]
class SaveTerm extends SunriceTool
{
    protected string $name = 'save_term';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'taxonomy' => ['nullable'],
            'id' => ['nullable', 'integer'],
            'action' => ['nullable', 'in:save,trash,restore'],
            'translations' => ['nullable', 'array'],
            'parent_id' => ['nullable', 'integer'],
            'template' => ['nullable', 'string'],
        ]);
        $term = empty($args['id']) ? null : Term::withTrashed()->find($args['id']);
        if (! empty($args['id']) && $term === null) {
            return $this->notFound('Term');
        }
        $taxonomy = $term?->taxonomy ?? $this->taxonomy($args['taxonomy'] ?? null);
        if ($taxonomy === null) {
            return $this->notFound('Taxonomy');
        }

        $action = $args['action'] ?? 'save';
        if ($action !== 'save') {
            if ($term === null) {
                return Response::error('Give the id of the term to '.$action.'.');
            }
            $this->authorize('delete', $term);
            $action === 'trash' ? app(TrashTerm::class)->handle($term) : app(RestoreTerm::class)->handle($term);

            return $this->json(['done' => $action, 'id' => $term->id]);
        }

        $term === null ? $this->authorize('create', [Term::class, $taxonomy->id]) : $this->authorize('update', $term);

        // Languages not sent keep their current values.
        $translations = [];
        foreach ($term?->translations ?? [] as $t) {
            $translations[$t->locale] = ['title' => $t->name, 'slug' => $t->slug, 'data' => $t->data ?? [], 'seo' => $t->seo ?? []];
        }
        foreach ((array) ($args['translations'] ?? []) as $locale => $t) {
            $t = (array) $t;
            $current = $translations[$locale] ?? [];
            $translations[$locale] = [
                'title' => $t['name'] ?? $t['title'] ?? $current['title'] ?? null,
                'slug' => $t['slug'] ?? $current['slug'] ?? null,
                'data' => array_replace((array) ($current['data'] ?? []), (array) ($t['data'] ?? [])),
                'seo' => array_replace((array) ($current['seo'] ?? []), (array) ($t['seo'] ?? [])),
            ];
        }
        if (! isset($translations[Locales::main()])) {
            return Response::error('Give the term\'s name in the main language ('.Locales::main().').');
        }

        $saved = app(SaveTermAction::class)->handle($taxonomy, array_filter([
            'parent_id' => array_key_exists('parent_id', $request->all()) ? ($args['parent_id'] ?? null) : $term?->parent_id,
            'translations' => $translations,
            'template' => $args['template'] ?? null,
        ], fn ($v, $k) => $k !== 'template' || $v !== null, ARRAY_FILTER_USE_BOTH), $term);

        return $this->json(['saved' => true, 'created' => $term === null] + Presenter::term($saved->load('translations')));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'taxonomy' => $schema->string()->description('Taxonomy handle, when creating.'),
            'id' => $schema->integer()->description('Term id, to update, trash or restore.'),
            'action' => $schema->string()->enum(['save', 'trash', 'restore'])->description('Default save.'),
            'translations' => $schema->object()->description('{locale: {name, slug, data, seo}}'),
            'parent_id' => $schema->integer()->nullable(),
            'template' => $schema->string()->description('Blade view for this term\'s page.'),
        ];
    }
}
