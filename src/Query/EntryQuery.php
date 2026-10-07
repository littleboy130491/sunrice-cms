<?php

declare(strict_types=1);

namespace Sunrice\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Sunrice\Cache\ContentCache;
use Sunrice\Fields\FieldRegistry;
use Sunrice\Fields\HydrationContext;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\Reference;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Models\TermTranslation;
use Sunrice\Support\Locales;

/**
 * Fluent public query over a collection's published entries. Used by
 * the Blade component, frontend controllers and developers:
 *
 *   EntryQuery::collection('articles')->locale('en')->where('price', '>', 10)
 *       ->whereTerm('categories', 'news')->orderBy('published_at', 'desc')
 *       ->limit(5)->with(['terms', 'assets'])->get();
 */
class EntryQuery
{
    /** @var Builder<Entry> */
    protected Builder $query;

    protected ?Collection $collection = null;

    protected string $locale;

    protected bool $preview = false;

    protected ?int $limit = null;

    /** @var array<int, string> */
    protected array $eager = ['translations'];

    protected bool $preloadAssets = false;

    protected bool $preloadTerms = false;

    /**
     * @param  array<string, mixed>  $state  extra state for the cache key
     */
    /** @var array<string, mixed> */
    protected array $filters = [];

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
        $this->locale = Locales::current();
        $this->query = Entry::query()->where('sunrice_entries.collection_id', $collection->id)->published();
    }

    public static function collection(string $handle): self
    {
        $collection = Collection::query()->where('handle', $handle)->firstOrFail();

        return new self($collection);
    }

    public static function forCollection(Collection $collection): self
    {
        return new self($collection);
    }

    public function locale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Include unpublished content (draft previews bypass published
     * rules and caches).
     */
    public function preview(bool $preview = true): static
    {
        $this->preview = $preview;
        if ($preview) {
            $this->query = Entry::query()->where('sunrice_entries.collection_id', $this->collection->id);
        }

        return $this;
    }

    /**
     * Filter on a standard column (published_at, sort_order, title,
     * author_id) or a custom field of the main translation's data.
     *
     * Custom-field filters use main-language values — the simplest
     * consistent rule across locales.
     */
    public function where(string $field, string $operator, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->filters['where'][] = [$field, $operator, $value];

        if ($this->isStandardColumn($field)) {
            $column = $field === 'title' ? 't.title' : 'sunrice_entries.'.$field;
            $this->query->where($column, $operator, $value);

            return $this;
        }

        $cast = $this->castFor($field);
        $this->joinTranslation();
        JsonField::where($this->query, 't.data', $field, $operator, $value, $cast);

        return $this;
    }

    /**
     * Filter to entries carrying a term (slug or id list) of a
     * taxonomy; includeChildren() adds descendant terms.
     */
    /** @param string|int|array<int, int|string> $term */
    public function whereTerm(string $taxonomyHandle, string|int|array $term, bool $includeChildren = false): static
    {
        $taxonomy = Taxonomy::query()->where('handle', $taxonomyHandle)->first();
        if ($taxonomy === null) {
            return $this;
        }

        $termIds = $this->termIds($taxonomy->id, $term);

        if ($includeChildren) {
            $withChildren = $termIds;
            foreach (Term::query()->whereIn('id', $termIds)->get() as $t) {
                $withChildren = array_merge($withChildren, $t->descendantIds());
            }
            $termIds = array_values(array_unique($withChildren));
        }

        $this->filters['terms'][] = [$taxonomyHandle, $term, $includeChildren];

        $this->query->whereHas('terms', fn (Builder $q) => $q->whereIn('sunrice_terms.id', $termIds));

        return $this;
    }

    /**
     * Reverse relationship through an `entries` field: entries whose
     * $field links to the given entry (or to any of several).
     *
     *     sunrice_entries('articles')->whereEntry('related_articles', $entry)->get();
     *
     * @param  Entry|int|array<int, Entry|int>  $entries
     */
    public function whereEntry(string $field, Entry|int|array $entries): static
    {
        $ids = [];
        foreach (is_array($entries) ? $entries : [$entries] as $entry) {
            $ids[] = $entry instanceof Entry ? (int) $entry->id : (int) $entry;
        }

        $this->filters['entry_fields'][] = [$field, $ids];

        return $this->whereFieldHolds($field, $ids);
    }

    /**
     * Entries whose `terms` field $field holds the given term(s): Term
     * models, ids or slugs (looked up in the field's taxonomy). Unlike
     * whereTerm(), which uses the taxonomies attached to the collection,
     * this reads a terms field in the blueprint.
     *
     *     sunrice_entries('projects')->whereFieldTerm('industries', $term)->get();
     *
     * @param  Term|int|string|array<int, Term|int|string>  $terms
     */
    public function whereFieldTerm(string $field, Term|int|string|array $terms, bool $includeChildren = false): static
    {
        $ids = [];
        $slugs = [];
        foreach (is_array($terms) ? $terms : [$terms] as $term) {
            match (true) {
                $term instanceof Term => $ids[] = (int) $term->id,
                is_int($term) => $ids[] = $term,
                default => $slugs[] = (string) $term,
            };
        }

        if ($slugs !== []) {
            $taxonomyHandle = $this->collection?->blueprint?->schema()->field($field)['config']['taxonomy'] ?? null;
            $taxonomy = is_string($taxonomyHandle) ? Taxonomy::query()->where('handle', $taxonomyHandle)->first() : null;
            $ids = array_merge($ids, TermTranslation::query()
                ->whereIn('slug', $slugs)
                ->when($taxonomy !== null, fn ($q) => $q->where('taxonomy_id', $taxonomy?->id))
                ->pluck('term_id')->map(fn ($id) => (int) $id)->all());
        }

        if ($includeChildren) {
            foreach (Term::query()->whereIn('id', $ids)->get() as $term) {
                $ids = array_merge($ids, $term->descendantIds());
            }
        }

        $ids = array_values(array_unique($ids));
        $this->filters['term_fields'][] = [$field, $ids, $includeChildren];

        return $this->whereFieldHolds($field, $ids);
    }

    /**
     * Entries whose id-list field $field (entries, terms, assets) holds any
     * of $ids, in the main translation's data.
     *
     * @param  array<int, int>  $ids
     */
    protected function whereFieldHolds(string $field, array $ids): static
    {
        JsonField::assertSafe($field);

        if ($ids === []) {
            $this->query->whereRaw('1 = 0'); // nothing given: nothing matches

            return $this;
        }

        $this->joinTranslation();
        $path = 't.data->'.str_replace('.', '->', $field);
        $this->query->where(function (Builder $q) use ($path, $ids): void {
            foreach ($ids as $id) {
                $q->orWhereJsonContains($path, $id);
            }
        });

        return $this;
    }

    /**
     * @param  string|int|array<int, int|string>  $term
     * @return array<int, int>
     */
    protected function termIds(int $taxonomyId, string|int|array $term): array
    {
        $terms = is_array($term) ? $term : [$term];

        $numeric = array_filter($terms, 'is_int');
        $slugs = array_filter($terms, 'is_string');

        $ids = $numeric;
        if ($slugs !== []) {
            $ids = array_merge($ids, TermTranslation::query()
                ->where('taxonomy_id', $taxonomyId)
                ->whereIn('slug', $slugs)
                ->pluck('term_id')->all());
        }

        return array_map('intval', $ids);
    }

    /**
     * Sort by a standard column (published_at, sort_order, title,
     * created_at) or a custom field. '-field' means descending.
     */
    public function orderBy(string $field, string $direction = 'asc'): static
    {
        if (str_starts_with($field, '-')) {
            $direction = 'desc';
            $field = substr($field, 1);
        }
        // 'manual' is the drag-and-drop order from the admin.
        if ($field === 'manual') {
            $field = 'sort_order';
        }

        $this->filters['order'][] = [$field, $direction];

        match ($field) {
            'published_at', 'sort_order', 'created_at', 'updated_at' => $this->query->orderBy('sunrice_entries.'.$field, $direction),
            'title' => tap($this->query, fn () => $this->joinTranslation())->orderBy('t.title', $direction),
            default => tap($this->query, function () use ($field, $direction): void {
                $this->joinTranslation();
                JsonField::orderBy($this->query, 't.data', $field, $this->castFor($field), $direction);
            }),
        };

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Eager-load extra relations ('terms', 'assets').
     *
     * @param  array<int, string>|string  $relations
     */
    public function with(array|string $relations): static
    {
        foreach ((array) $relations as $relation) {
            match ($relation) {
                'assets' => $this->preloadAssets = true,
                'terms' => $this->preloadTerms = true,
                default => $this->eager[] = $relation,
            };
        }

        return $this;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Entry>
     */
    public function get(): \Illuminate\Support\Collection
    {
        $this->applyDefaultOrder();

        $results = $this->runQuery(
            fn () => ($this->limit ? $this->query->limit($this->limit) : $this->query)->get(),
        );

        return $this->resolve($results);
    }

    /** @return LengthAwarePaginator<int, Entry> */
    public function paginate(int $perPage = 12, string $pageName = 'page'): LengthAwarePaginator
    {
        $this->applyDefaultOrder();
        $this->filters['paginate'] = [$perPage, $pageName, (int) request($pageName, 1)];

        $paginator = $this->runQuery(
            fn () => $this->query->paginate($perPage, ['sunrice_entries.*'], $pageName)->withQueryString(),
        );

        $this->resolve($paginator->getCollection());

        return $paginator;
    }

    /**
     * The query builder — public escape hatch.
     *
     * @return Builder<Entry>
     */
    public function toBase(): Builder
    {
        return clone $this->query;
    }

    // ---- internals -----------------------------------------------------------

    /**
     * Without an explicit orderBy(), use the collection's default order
     * (Structure → Collections → Order).
     */
    protected function applyDefaultOrder(): void
    {
        if (! empty($this->filters['order']) || ! empty($this->filters['default_order'])) {
            return;
        }

        [$column, $direction] = $this->collection?->defaultSort() ?? ['published_at', 'desc'];
        $this->filters['default_order'] = [$column, $direction];
        $this->orderBy($column, $direction);
        unset($this->filters['order']);
        // Manual order: oldest first on ties, as the admin's drag-and-drop numbers them.
        $this->query->orderBy('sunrice_entries.id', $column === 'sort_order' ? 'asc' : 'desc');
    }

    protected function isStandardColumn(string $field): bool
    {
        return in_array($field, ['published_at', 'sort_order', 'created_at', 'updated_at', 'status', 'author_id', 'title'], true);
    }

    protected function joinTranslation(): void
    {
        if (! collect($this->query->getQuery()->joins ?? [])->contains(fn ($j) => str_contains((string) $j->table, 'as t'))) {
            $this->query->leftJoin(
                'sunrice_entry_translations as t',
                fn ($join) => $join->on('t.entry_id', '=', 'sunrice_entries.id')
                    ->where('t.locale', '=', Locales::main()),
            )->select('sunrice_entries.*');
        }
    }

    /**
     * Sort/filter cast for a custom field from the collection's
     * blueprint (string | number | date | null).
     */
    protected function castFor(string $field): ?string
    {
        $definition = $this->collection?->blueprint?->schema()->field($field);
        if ($definition === null) {
            return null;
        }

        return app(FieldRegistry::class)
            ->get($definition['type'] ?? 'text')
            ->sortCast();
    }

    protected function runQuery(\Closure $callback): mixed
    {
        if ($this->preview || ! ContentCache::enabled()) {
            return $callback();
        }

        $key = 'entryquery:'.md5(json_encode([
            'collection' => $this->collection?->id,
            'locale' => $this->locale,
            'filters' => $this->filters,
            'limit' => $this->limit,
            'eager' => $this->eager,
        ]));

        // Cache stores serialized models; acceptable and portable.
        return ContentCache::remember($key, $callback, $this->locale);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Entry>  $entries
     * @return \Illuminate\Support\Collection<int, Entry>
     */
    protected function resolve(\Illuminate\Support\Collection $entries): \Illuminate\Support\Collection
    {
        if ($entries instanceof \Illuminate\Database\Eloquent\Collection) {
            $entries->loadMissing(array_unique($this->eager));
        }

        $ctx = new HydrationContext($this->locale, $this->preview);

        if ($this->preloadAssets || $this->preloadTerms) {
            $translationIds = $entries->flatMap(fn (Entry $e) => $e->translations->pluck('id'))->all();
            $refs = Reference::query()
                ->where('source_type', 'entry')
                ->whereIn('source_id', $translationIds)
                ->get(['target_type', 'target_id']);

            if ($this->preloadAssets) {
                $ctx->preloadAssets($refs->where('target_type', 'asset')->pluck('target_id')->all());
            }
            if ($this->preloadTerms) {
                $ctx->preloadTerms($refs->where('target_type', 'term')->pluck('target_id')->all());
            }
        }

        $entries->each(function (Entry $entry) use ($ctx): void {
            $entry->resolveFor($this->locale);
            $entry->hydrationContext = $ctx;
        });

        return $entries;
    }
}
