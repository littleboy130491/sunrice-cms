<?php

declare(strict_types=1);

namespace Sunrice\View\Components;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Sunrice\Models\Collection as CollectionModel;
use Sunrice\Models\Entry;
use Sunrice\Query\EntryQuery;

/**
 * `<x-sunrice::entries>` — fetch and iterate a collection's published
 * entries in Blade. Exposes the public $entries property (Collection
 * or LengthAwarePaginator).
 */
class Entries extends Component
{
    /** @var Collection<int, Entry>|LengthAwarePaginator<int, Entry> */
    public Collection|LengthAwarePaginator $entries;

    /**
     * @param  string  $collection  collection handle
     * @param  bool  $paginate  paginate results (default false)
     * @param  int|null  $perPage  items per page (default collection per_page or 12)
     * @param  string|null  $pageName  query-string page name (default {collection}_page)
     * @param  int|null  $limit  hard limit for non-paginated queries
     * @param  array<string, mixed>|string  $where  filters: [field => value] or [[field, op, value], ...]
     * @param  array<string, string|array<int, string>>|string  $terms  taxonomy => slug(s)
     * @param  string|null  $orderBy  e.g. 'published_at', '-price' or 'published_at desc'
     * @param  string|null  $with  comma-separated relations to eager load
     */
    public function __construct(
        string $collection,
        bool $paginate = false,
        ?int $perPage = null,
        ?string $pageName = null,
        ?int $limit = null,
        array|string $where = [],
        array|string $terms = [],
        ?string $orderBy = null,
        ?string $with = null,
    ) {
        $model = CollectionModel::query()->where('handle', $collection)->firstOrFail();

        $query = EntryQuery::forCollection($model);

        foreach ($this->normalizeWheres($where) as [$field, $operator, $value]) {
            $query->where($field, $operator, $value);
        }

        foreach ($this->normalizeTerms($terms) as $taxonomy => $slugs) {
            $query->whereTerm($taxonomy, $slugs, includeChildren: true);
        }

        // '-published_at', 'published_at desc' or 'title asc'; several
        // comma-separated ('-published_at, title').
        foreach (array_filter(array_map('trim', explode(',', (string) $orderBy))) as $order) {
            $parts = preg_split('/\s+/', $order) ?: [$order];
            $field = ltrim($parts[0], '-');
            $direction = str_starts_with($parts[0], '-') || strtolower($parts[1] ?? '') === 'desc' ? 'desc' : 'asc';
            $query->orderBy($field, $direction);
        }

        if ($with !== null && $with !== '') {
            $query->with(array_map('trim', explode(',', $with)));
        }

        if ($paginate) {
            $this->entries = $query->paginate(
                $perPage ?? (int) $model->setting('per_page', 12),
                $pageName ?? $collection.'_page',
            )->withQueryString();
        } else {
            if ($limit !== null) {
                $query->limit($limit);
            }
            $this->entries = $query->get();
        }
    }

    public function render(): string
    {
        return 'sunrice::components.entries';
    }

    /**
     * @param  array<string, mixed>|string  $where
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    protected function normalizeWheres(array|string $where): array
    {
        if (is_string($where) && trim($where) !== '') {
            $where = json_decode($where, true) ?? [];
        }
        if (! is_array($where)) {
            return [];
        }

        $wheres = [];
        foreach ($where as $key => $value) {
            if (is_int($key)) {
                // [field, value] or [field, operator, value]
                $tuple = array_values((array) $value);
                $wheres[] = count($tuple) === 2
                    ? [(string) $tuple[0], '=', $tuple[1]]
                    : [(string) $tuple[0], (string) $tuple[1], $tuple[2] ?? null];
            } else {
                $wheres[] = [(string) $key, '=', $value];
            }
        }

        return $wheres;
    }

    /**
     * @param  array<string, string|array<int, string>>|string  $terms
     * @return array<string, string|array<int, string>>
     */
    protected function normalizeTerms(array|string $terms): array
    {
        if (is_string($terms) && trim($terms) !== '') {
            $terms = json_decode($terms, true) ?? [];
        }

        return is_array($terms) ? $terms : [];
    }
}
