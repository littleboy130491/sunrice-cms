<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sunrice\Models\Collection;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;

class TermSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $taxonomy = $request->input('taxonomy');
        $q = (string) $request->input('q', '');

        $terms = Term::query()
            ->whereIn('taxonomy_id', $this->allowedTaxonomyIds($request))
            ->when($taxonomy, fn ($query) => $query->whereHas('taxonomy', fn ($t) => $t->where('handle', $taxonomy)))
            ->with('translations')
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('translations', fn ($t) => $t->whereLike('name', "%{$q}%"));
            })
            ->orderBy('sort_order')
            ->limit(50)
            ->get()
            ->map(fn (Term $t) => [
                'id' => $t->id,
                'title' => $t->translations->firstWhere('locale', Locales::main())->name ?? "Term #{$t->id}",
            ])
            ->values();

        return response()->json(['data' => $terms]);
    }

    /**
     * Taxonomies whose terms the user may list: those they can view, plus
     * those attached to a collection whose entries they can create or edit
     * (so entry editors can tag without term-management permissions).
     *
     * @return array<int, int>
     */
    protected function allowedTaxonomyIds(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return Taxonomy::query()
            ->with('collections:id')
            ->get(['id'])
            ->filter(fn (Taxonomy $taxonomy) => $user->can('view', [Term::class, $taxonomy->id])
                || $taxonomy->collections->contains(fn (Collection $collection) => $user->can("sunrice.entries.{$collection->id}.create")
                    || $user->can("sunrice.entries.{$collection->id}.edit")
                    || $user->can("sunrice.entries.{$collection->id}.edit-own")))
            ->pluck('id')
            ->all();
    }
}
