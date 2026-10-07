<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin\Structure;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Taxonomies\DeleteTaxonomy;
use Sunrice\Actions\Taxonomies\SaveTaxonomy;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Taxonomy;

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

        // Same handle as a deleted taxonomy: it came back with its terms.
        $message = $taxonomy->wasRecentlyCreated
            ? "Taxonomy \"{$taxonomy->title}\" created."
            : "Taxonomy \"{$taxonomy->title}\" restored with its ".trans_choice('{0} no terms|{1} 1 term|[2,*] :count terms', $taxonomy->terms()->count()).'.';

        return redirect()->route('sunrice.admin.structure.taxonomies.index')->with('success', $message);
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
        app(DeleteTaxonomy::class)->handle($taxonomy);

        return redirect()->route('sunrice.admin.structure.taxonomies.index')
            ->with('success', "Taxonomy \"{$taxonomy->title}\" deleted. Its terms are hidden, not erased: create a taxonomy with the handle \"{$taxonomy->handle}\" to bring them back.");
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
