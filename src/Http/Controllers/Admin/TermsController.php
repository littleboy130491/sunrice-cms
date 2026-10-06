<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Support\Reorder;
use Sunrice\Actions\Taxonomies\SaveTerm;
use Sunrice\Actions\Taxonomies\TrashTerm;
use Sunrice\Admin\Table\Column;
use Sunrice\Frontend\UrlGenerator;
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

        $main = Locales::main();
        $locales = Locales::available();
        $all = Term::query()
            ->where('taxonomy_id', $taxonomy->id)
            ->with('translations')
            ->withCount('entries')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $ordered = $this->treeOrder($all);
        $titleOf = fn (?Term $t) => $t === null ? null : ($t->translations->firstWhere('locale', $main)->name ?? $t->translations->first()->name ?? '#'.$t->id);
        $byId = $all->keyBy('id');

        // Search, filter and sort in PHP: the tree order (parents before
        // their children) isn't expressible in SQL, and terms are few.
        $search = trim((string) $request->query('search', '')) ?: null;
        $filters = array_filter((array) $request->query('filters', []), fn ($v) => $v !== null && $v !== '');
        $sort = trim((string) $request->query('sort', '')) ?: null;

        $rows = $ordered->filter(function (array $item) use ($search, $filters): bool {
            /** @var Term $term */
            $term = $item['term'];
            if ($search !== null && ! $term->translations->contains(
                fn (TermTranslation $t) => str_contains(mb_strtolower($t->name.' '.$t->slug), mb_strtolower($search)),
            )) {
                return false;
            }
            $parent = $filters['parent'] ?? null;
            if ($parent === 'root' && $term->parent_id !== null) {
                return false;
            }
            if ($parent !== null && $parent !== 'root' && (int) $term->parent_id !== (int) $parent) {
                return false;
            }
            $missing = $filters['missing'] ?? null;
            if ($missing !== null && $term->translations->contains('locale', $missing)) {
                return false;
            }

            return true;
        });

        if ($sort !== null) {
            $direction = str_starts_with($sort, '-') ? -1 : 1;
            $key = ltrim($sort, '-');
            $value = fn (array $item) => match ($key) {
                'entries' => $item['term']->entries_count,
                'created_at' => $item['term']->created_at?->getTimestamp() ?? 0,
                default => mb_strtolower((string) $titleOf($item['term'])),
            };
            $rows = $rows->sort(fn ($a, $b) => $direction * ($value($a) <=> $value($b)));
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $page = max(1, (int) $request->query('page', 1));
        $urls = app(UrlGenerator::class);
        $hasPages = (bool) $taxonomy->setting('has_archive');
        $paginator = new LengthAwarePaginator(
            $rows->values()->forPage($page, $perPage)->map(function (array $item) use ($titleOf, $byId, $taxonomy, $hasPages, $urls, $main): array {
                /** @var Term $t */
                $t = $item['term'];

                return [
                    'id' => $t->id,
                    'title' => $titleOf($t),
                    'depth' => $item['depth'],
                    'slug' => $t->translations->firstWhere('locale', $main)->slug ?? '',
                    'parent' => $titleOf($byId->get($t->parent_id)) ?? '—',
                    'entries' => $t->entries_count,
                    'languages' => $t->translations->pluck('locale')->map(fn ($l) => strtoupper((string) $l))->implode(' '),
                    'created_at' => $t->created_at?->format('j M Y'),
                    // The term's page, when the taxonomy has term pages.
                    'url' => $hasPages ? $urls->term($t->setRelation('taxonomy', $taxonomy), $main) : null,
                ];
            })->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $columns = array_values(array_filter([
            new Column('title', 'Title', sortable: true),
            new Column('slug', 'Slug'),
            $taxonomy->hierarchical ? new Column('parent', 'Parent') : null,
            new Column('entries', 'Entries', sortable: true, type: 'number'),
            count($locales) > 1 ? new Column('languages', 'Languages') : null,
            new Column('created_at', 'Created', sortable: true, type: 'date'),
        ]));

        $reorderable = $search === null && $filters === [] && $sort === null
            && $request->user()->can('reorder', [Term::class, $taxonomy->id]);

        return Inertia::render('Taxonomies/Terms', [
            'taxonomy' => $taxonomy->only('id', 'handle', 'title', 'hierarchical', 'blueprint_id'),
            // Every term: the editor's parent picker and the term being edited.
            'terms' => $all->map(fn (Term $t) => [
                'id' => $t->id,
                'parent_id' => $t->parent_id,
                'translations' => $t->translations->keyBy('locale')->map(fn (TermTranslation $tr) => [
                    'title' => $tr->name,
                    'slug' => $tr->slug,
                    'data' => $tr->data,
                ]),
                'count' => $t->entries_count,
            ]),
            'columns' => $columns,
            'rows' => $paginator,
            'meta' => ['search' => $search, 'filters' => $filters, 'sort' => $sort],
            'parents' => $taxonomy->hierarchical
                ? $ordered->filter(fn (array $i) => $all->contains('parent_id', $i['term']->id))
                    ->map(fn (array $i) => ['value' => (string) $i['term']->id, 'label' => str_repeat('— ', $i['depth']).$titleOf($i['term'])])->values()
                : [],
            'reorderable' => $reorderable,
            'locales' => $locales,
            'mainLocale' => $main,
            'blueprint' => $taxonomy->blueprint?->schema()->toAdminTabs(),
        ]);
    }

    /**
     * Terms in tree order: each parent followed by its children, siblings
     * by sort_order. Orphans (parent missing) count as top level.
     *
     * @param  EloquentCollection<int, Term>  $terms
     * @return Collection<int, array{term: Term, depth: int}>
     */
    protected function treeOrder(EloquentCollection $terms): Collection
    {
        $ids = $terms->pluck('id')->all();
        $children = $terms->groupBy(fn (Term $t) => $t->parent_id !== null && in_array($t->parent_id, $ids, true) ? $t->parent_id : 0);
        $out = collect();
        $walk = function (int $parent, int $depth) use (&$walk, $children, $out): void {
            foreach ($children->get($parent, []) as $term) {
                $out->push(['term' => $term, 'depth' => $depth]);
                $walk($term->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $out;
    }

    public function bulk(Request $request, Taxonomy $taxonomy): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:delete'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);
        $terms = Term::query()->where('taxonomy_id', $taxonomy->id)->whereIn('id', $validated['ids'])->get();
        foreach ($terms as $term) {
            $this->authorize('delete', $term);
        }
        foreach ($terms as $term) {
            if ($term->exists && Term::query()->whereKey($term->id)->exists()) {
                app(TrashTerm::class)->handle($term);
            }
        }

        return back()->with('success', $terms->count() === 1 ? 'Term deleted.' : "{$terms->count()} terms deleted.");
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
        $this->authorize('reorder', [Term::class, $taxonomy->id]);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['integer'],
        ]);
        // The posted ids may be one page of the list: they take the
        // positions those terms hold in the full order, in the new order.
        $full = $this->treeOrder(Term::query()->where('taxonomy_id', $taxonomy->id)->orderBy('sort_order')->orderBy('id')->get())
            ->map(fn (array $i) => $i['term']->id)->all();
        $posted = array_values(array_intersect(array_map('intval', $validated['items']), $full));
        $slots = array_values(array_intersect($full, $posted));
        $order = $full;
        foreach ($slots as $n => $id) {
            $order[array_search($id, $full, true)] = $posted[$n];
        }
        $reorder->handle(Term::class, array_values($order));

        return back();
    }
}
