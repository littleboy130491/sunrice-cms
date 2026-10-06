<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin\Structure;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Taxonomies\SaveTaxonomy;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Taxonomy;
use Sunrice\Permissions\SyncPermissions;

class TaxonomiesController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Taxonomy::class, 'taxonomy');
    }

    public function index(): Response
    {
        return Inertia::render('Structure/Taxonomies/Index', [
            'taxonomies' => Taxonomy::query()
                ->withCount('terms')
                ->orderBy('title')
                ->get()
                ->map(fn (Taxonomy $t) => [
                    'id' => $t->id,
                    'handle' => $t->handle,
                    'title' => $t->title,
                    'hierarchical' => (bool) $t->hierarchical,
                    'terms_count' => $t->terms_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): RedirectResponse
    {
        $taxonomy = app(SaveTaxonomy::class)->handle($request->all());

        return redirect()->route('sunrice.admin.structure.taxonomies.index')
            ->with('success', "Taxonomy \"{$taxonomy->title}\" created.");
    }

    public function edit(Taxonomy $taxonomy): Response
    {
        return $this->form($taxonomy);
    }

    public function update(Request $request, Taxonomy $taxonomy): RedirectResponse
    {
        app(SaveTaxonomy::class)->handle($request->all(), $taxonomy);

        return redirect()->route('sunrice.admin.structure.taxonomies.index')
            ->with('success', "Taxonomy \"{$taxonomy->title}\" saved.");
    }

    public function destroy(Taxonomy $taxonomy): RedirectResponse
    {
        $taxonomy->delete();
        app(SyncPermissions::class)->handle();
        ContentChanged::dispatch('taxonomy_deleted');

        return redirect()->route('sunrice.admin.structure.taxonomies.index')
            ->with('success', "Taxonomy \"{$taxonomy->title}\" deleted.");
    }

    protected function form(?Taxonomy $taxonomy): Response
    {
        return Inertia::render('Structure/Taxonomies/Form', [
            'taxonomy' => $taxonomy === null ? null : [
                ...$taxonomy->only('id', 'handle', 'title', 'blueprint_id', 'hierarchical', 'settings'),
                'collection_ids' => $taxonomy->collections()->pluck('sunrice_collections.id'),
            ],
            'blueprints' => Blueprint::query()->orderBy('title')->get(['id', 'title', 'handle']),
            'collections' => Collection::query()->orderBy('sort_order')->orderBy('title')->get(['id', 'title', 'handle']),
        ]);
    }
}
