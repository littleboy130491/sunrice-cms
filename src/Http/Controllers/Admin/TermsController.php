<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Support\Reorder;
use Sunrice\Actions\Taxonomies\SaveTerm;
use Sunrice\Actions\Taxonomies\TrashTerm;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Models\TermTranslation;
use Sunrice\Support\Locales;

class TermsController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Taxonomy $taxonomy): Response
    {
        $this->authorize('viewAny', [Term::class, $taxonomy->id]);

        $terms = Term::query()
            ->where('taxonomy_id', $taxonomy->id)
            ->with(['translations', 'parent:id'])
            ->when($request->input('search'), function ($q, string $search) {
                $q->whereHas('translations', fn ($t) => $t->whereLike('name', "%{$search}%"));
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Taxonomies/Terms', [
            'taxonomy' => $taxonomy->only('id', 'handle', 'title', 'hierarchical', 'blueprint_id'),
            'terms' => $terms->map(fn (Term $t) => [
                'id' => $t->id,
                'parent_id' => $t->parent_id,
                'translations' => $t->translations->keyBy('locale')->map(fn (TermTranslation $tr) => [
                    'title' => $tr->name,
                    'slug' => $tr->slug,
                    'data' => $tr->data,
                ]),
                'count' => $t->entries()->count(),
            ]),
            'locales' => Locales::available(),
            'blueprint' => $taxonomy->blueprint?->schema()->toAdminSchema(),
        ]);
    }

    public function store(Request $request, Taxonomy $taxonomy, SaveTerm $save): RedirectResponse
    {
        $this->authorize('create', [Term::class, $taxonomy->id]);

        $save->handle($taxonomy, $request->all());

        return back()->with('success', 'Term created.');
    }

    public function update(Request $request, Term $term, SaveTerm $save): RedirectResponse
    {
        $this->authorize('update', $term);

        $save->handle($term->taxonomy, $request->all(), $term);

        return back()->with('success', 'Term saved.');
    }

    public function destroy(Term $term): RedirectResponse
    {
        $this->authorize('delete', $term);

        app(TrashTerm::class)->handle($term);

        return back()->with('success', 'Term deleted.');
    }

    public function reorder(Request $request, Taxonomy $taxonomy, Reorder $reorder): RedirectResponse
    {
        $this->authorize('viewAny', [Term::class, $taxonomy->id]);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['integer'],
        ]);
        $reorder->handle(Term::class, $validated['items']);

        return back();
    }
}
