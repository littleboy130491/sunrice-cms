<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin\Structure;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Structure\DeleteBlueprint;
use Sunrice\Actions\Structure\SaveBlueprint;
use Sunrice\Fields\FieldRegistry;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Fieldset;

class BlueprintsController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Blueprint::class, 'blueprint');
    }

    public function index(): Response
    {
        $listings = [];
        foreach (Collection::query()->get(['id', 'title', 'settings']) as $collection) {
            if ($id = $collection->setting('archive_blueprint_id')) {
                $listings[(int) $id][] = ['type' => 'Archive/listing page', 'title' => $collection->title];
            }
        }

        return Inertia::render('Structure/Blueprints/Index', [
            'blueprints' => Blueprint::query()
                ->with(['collections:id,title,blueprint_id', 'taxonomies:id,title,blueprint_id', 'globalSets:id,title,blueprint_id'])
                ->orderBy('title')
                ->get()
                ->map(fn (Blueprint $b) => [
                    'id' => $b->id,
                    'handle' => $b->handle,
                    'title' => $b->title,
                    'fields_count' => count($b->fields ?? []),
                    // Where it's used, by name: collections, taxonomies, globals, listing pages.
                    'used_by' => [
                        ...$b->collections->map(fn ($c) => ['type' => 'Collection', 'title' => $c->title]),
                        ...$b->taxonomies->map(fn ($t) => ['type' => 'Taxonomy', 'title' => $t->title]),
                        ...$b->globalSets->map(fn ($g) => ['type' => 'Global', 'title' => $g->title]),
                        ...($listings[$b->id] ?? []),
                    ],
                ]),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request, SaveBlueprint $save): RedirectResponse
    {
        $blueprint = $save->handle($request->all());

        return redirect()->route('sunrice.admin.structure.blueprints.edit', $blueprint)
            ->with('success', "Blueprint \"{$blueprint->title}\" created.");
    }

    public function edit(Blueprint $blueprint): Response
    {
        return $this->form($blueprint);
    }

    public function update(Request $request, Blueprint $blueprint, SaveBlueprint $save): RedirectResponse
    {
        $save->handle($request->all(), $blueprint);

        return back()->with('success', "Blueprint \"{$blueprint->title}\" saved.");
    }

    public function destroy(Blueprint $blueprint, DeleteBlueprint $delete): RedirectResponse
    {
        try {
            $delete->handle($blueprint);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sunrice.admin.structure.blueprints.index')
            ->with('success', "Blueprint \"{$blueprint->title}\" deleted.");
    }

    protected function form(?Blueprint $blueprint): Response
    {
        $registry = app(FieldRegistry::class);

        return Inertia::render('Structure/Blueprints/Form', [
            'blueprint' => $blueprint?->only('id', 'handle', 'title', 'fields'),
            'fieldsets' => Fieldset::query()->orderBy('title')->get(['id', 'title', 'handle']),
            'fieldTypes' => collect($registry->all())->map(fn ($t) => [
                'type' => $t->type(),
                'translatable' => $t->translatableByDefault(),
                'settings' => $t->settingsSchema(),
            ])->values(),
        ]);
    }
}
