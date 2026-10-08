<?php

declare(strict_types=1);

namespace Sunrice\View\Components;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Sunrice\Frontend\TemplateContext;
use Sunrice\Models\Collection as CollectionModel;
use Sunrice\Models\Entry;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;

/**
 * `<x-sunrice::terms>` — fetch a taxonomy's terms in Blade, like
 * `<x-sunrice::entries>` does for entries. Renders only its slot; the
 * terms are on `$component->terms`, each resolved in the page language
 * (name, slug, url, get('field')) with `entries_count` (published
 * entries, of `collection` when given).
 */
class Terms extends Component
{
    /** @var Collection<int, Term> */
    public Collection $terms;

    protected ?CollectionModel $collectionModel = null;

    protected ?int $currentTermId = null;

    /**
     * @param  string  $taxonomy  taxonomy handle
     * @param  string|null  $collection  count and link entries of this collection only
     * @param  int|string|null  $parent  "root" for top-level terms, or a term slug / id for its children
     * @param  bool  $tree  nest children under their parents (`$term->children`); the list holds the top level
     * @param  bool  $hideEmpty  leave out terms without published entries
     * @param  string|null  $orderBy  manual (default), name, entries, created_at; "-" for descending
     * @param  int|null  $limit  at most this many terms (top level when nested)
     * @param  Entry|int|null  $entry  only the terms of this entry
     */
    public function __construct(
        string $taxonomy,
        ?string $collection = null,
        int|string|null $parent = null,
        bool $tree = false,
        bool $hideEmpty = false,
        ?string $orderBy = null,
        ?int $limit = null,
        Entry|int|null $entry = null,
    ) {
        $model = Taxonomy::query()->where('handle', $taxonomy)->firstOrFail();
        $this->collectionModel = $collection === null ? null : CollectionModel::query()->where('handle', $collection)->firstOrFail();
        $locale = Locales::current();

        $query = Term::query()->where('taxonomy_id', $model->id)->with('translations')
            ->withCount(['entries as entries_count' => function (Builder $q): void {
                $q->where('sunrice_entries.status', 'published')->where('sunrice_entries.published_at', '<=', now());
                if ($this->collectionModel !== null) {
                    $q->where('sunrice_entries.collection_id', $this->collectionModel->id);
                }
            }]);
        if ($entry !== null) {
            $entryId = $entry instanceof Entry ? $entry->id : $entry;
            $query->whereHas('entries', fn (Builder $q) => $q->where('sunrice_entries.id', $entryId));
        }

        /** @var Collection<int, Term> $all */
        $all = $query->get()->each(function (Term $term) use ($locale, $model): void {
            $term->setRelation('taxonomy', $model);
            $term->resolveFor($locale);
        });

        $all = $this->sorted($all, $orderBy);
        $byParent = $all->groupBy(fn (Term $t) => (int) $t->parent_id);

        $parentId = $this->parentId($all, $parent);
        $list = match (true) {
            $parentId !== null => $byParent->get($parentId, collect()),
            $parent === 'root' || $tree => $all->filter(fn (Term $t) => $t->parent_id === null || ! $all->contains('id', $t->parent_id)),
            default => $all,
        };

        if ($tree) {
            $nest = function (Term $term) use (&$nest, $byParent, $hideEmpty): bool {
                $children = $byParent->get($term->id, collect())->filter(fn (Term $child) => $nest($child))->values();
                $term->setRelation('children', $children);

                return ! $hideEmpty || $term->entries_count > 0 || $children->isNotEmpty();
            };
            $list = $list->filter(fn (Term $term) => $nest($term));
        } elseif ($hideEmpty) {
            $list = $list->filter(fn (Term $term) => $term->entries_count > 0);
        }

        $list = $list->values();
        $this->terms = $limit === null ? $list : $list->take($limit)->values();

        $page = request()->attributes->get('sunrice.page');
        $this->currentTermId = $page instanceof TemplateContext ? $page->term?->id : null;
    }

    /**
     * The term's page; with `collection`, its page for that collection's
     * entries (e.g. /blog/category/news).
     */
    public function url(Term $term): ?string
    {
        return $this->collectionModel !== null ? $term->urlIn($this->collectionModel) : $term->url;
    }

    /** Whether the page being shown is this term's page. */
    public function isCurrent(Term $term): bool
    {
        return $this->currentTermId !== null && $this->currentTermId === $term->id;
    }

    public function render(): string
    {
        return 'sunrice::components.entries';
    }

    /**
     * @param  Collection<int, Term>  $terms
     * @return Collection<int, Term>
     */
    protected function sorted(Collection $terms, ?string $orderBy): Collection
    {
        $orderBy = trim((string) $orderBy) ?: 'manual';
        $descending = str_starts_with($orderBy, '-');
        $key = ltrim($orderBy, '-');

        $value = match ($key) {
            'name', 'title' => fn (Term $t) => mb_strtolower((string) $t->name),
            'entries', 'count' => fn (Term $t) => (int) $t->entries_count,
            'created_at' => fn (Term $t) => $t->created_at?->getTimestamp() ?? 0,
            default => fn (Term $t) => [(int) $t->sort_order, mb_strtolower((string) $t->name)],
        };

        return ($descending ? $terms->sortByDesc($value) : $terms->sortBy($value))->values();
    }

    /** @param Collection<int, Term> $terms */
    protected function parentId(Collection $terms, int|string|null $parent): ?int
    {
        if ($parent === null || $parent === '' || $parent === 'root') {
            return null;
        }
        if (is_int($parent) || ctype_digit($parent)) {
            return (int) $parent;
        }

        $found = $terms->first(fn (Term $t) => $t->slug === $parent || $t->mainTranslation()?->slug === $parent);

        return $found?->id ?? -1;
    }
}
