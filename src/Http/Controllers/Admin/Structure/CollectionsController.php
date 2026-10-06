<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin\Structure;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Structure\DeleteCollection;
use Sunrice\Actions\Structure\SaveCollection;
use Sunrice\Actions\Support\Reorder;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Taxonomy;

class CollectionsController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Collection::class, 'collection');
    }

    public function index(): Response
    {
        return Inertia::render('Structure/Collections/Index', [
            'collections' => Collection::query()
                ->with(['blueprint:id,title,handle', 'taxonomies:id,title,handle'])
                ->withCount('entries')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (Collection $c) => [
                    'id' => $c->id,
                    'handle' => $c->handle,
                    'title' => $c->title,
                    'blueprint' => $c->blueprint?->only('id', 'title', 'handle'),
                    'taxonomies' => $c->taxonomies->map->only('id', 'title'),
                    'entries_count' => $c->entries_count,
                    'settings' => $c->settings,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Structure/Collections/Form', [
            'collection' => null,
            'blueprints' => Blueprint::query()->orderBy('title')->get(['id', 'title', 'handle']),
            'taxonomies' => Taxonomy::query()->orderBy('title')->get(['id', 'title', 'handle']),
        ]);
    }

    public function store(Request $request, SaveCollection $save): RedirectResponse
    {
        $collection = $save->handle($request->all());

        return redirect()->route('sunrice.admin.structure.collections.index')
            ->with('success', "Collection \"{$collection->title}\" created.");
    }

    public function edit(Collection $collection): Response
    {
        return Inertia::render('Structure/Collections/Form', [
            'collection' => $collection->load('taxonomies:id')->only(
                'id', 'handle', 'title', 'blueprint_id', 'settings',
            ) + ['taxonomy_ids' => $collection->taxonomies->pluck('id')],
            'blueprints' => Blueprint::query()->orderBy('title')->get(['id', 'title', 'handle']),
            'taxonomies' => Taxonomy::query()->orderBy('title')->get(['id', 'title', 'handle']),
        ]);
    }

    public function update(Request $request, Collection $collection, SaveCollection $save): RedirectResponse
    {
        $save->handle($request->all(), $collection);

        return redirect()->route('sunrice.admin.structure.collections.index')
            ->with('success', "Collection \"{$collection->title}\" saved.");
    }

    public function destroy(Collection $collection, DeleteCollection $delete): RedirectResponse
    {
        $delete->handle($collection);

        return back()->with('success', "Collection \"{$collection->title}\" deleted.");
    }

    public function reorder(Request $request, Reorder $reorder): RedirectResponse
    {
        $this->authorize('update', Collection::class);

        $validated = $request->validate(['items' => ['required', 'array'], 'items.*' => ['integer']]);
        $reorder->handle(Collection::class, $validated['items']);

        return back();
    }
}
