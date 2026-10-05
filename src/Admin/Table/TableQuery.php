<?php

declare(strict_types=1);

namespace Sunrice\Admin\Table;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Sunrice\Query\JsonField;

/**
 * Applies DataTable request parameters to an Eloquent builder:
 * search, filters (incl. JSON fields and trashed), sorting and
 * per_page. Shared by every admin index endpoint and CSV export.
 */
class TableQuery
{
    /** @var array<int, string> columns and JSON paths searched with `?q=` */
    protected array $searchable = [];

    /** @var array<int, string> fields that may be filtered on */
    protected array $filterable = [];

    /** @var array<int, string> fields that may be sorted on */
    protected array $sortable = [];

    /** @var array<string, string> JSON fields (handle => cast) */
    protected array $jsonColumns = [];

    /** @var array<string, string> JSON column data expression (handle => 't.data') */
    protected array $jsonColumnSource = [];

    protected string $dataColumn = 'data';

    protected int $defaultPerPage = 20;

    /** @var array<string, mixed> */
    protected array $activeFilters = [];

    protected ?string $sort = null;

    protected ?string $search = null;

    /** @param Builder<Model> $query */
    public function __construct(protected Builder $query) {}

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function for(Builder $query): self
    {
        /** @var Builder<Model> $query */
        return new self($query);
    }

    /**
     * @param  array<int, string>  $columns  column names or `data.field` JSON paths
     */
    public function searchable(array $columns): static
    {
        $this->searchable = $columns;

        return $this;
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function filterable(array $fields): static
    {
        $this->filterable = $fields;

        return $this;
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function sortable(array $fields): static
    {
        $this->sortable = $fields;

        return $this;
    }

    /**
     * Mark fields as JSON paths inside $dataColumn (default `data`),
     * optionally with a cast for comparisons/sorting.
     *
     * @param  array<string|int, string>  $fields  [handle => cast] or [handle]
     */
    public function jsonFields(array $fields, string $dataColumn = 'data'): static
    {
        $this->dataColumn = $dataColumn;
        foreach ($fields as $key => $cast) {
            $handle = is_int($key) ? $cast : $key;
            $this->jsonColumns[$handle] = is_int($key) ? 'string' : $cast;
        }

        return $this;
    }

    public function defaultPerPage(int $perPage): static
    {
        $this->defaultPerPage = $perPage;

        return $this;
    }

    /**
     * The JSON source column, when data lives on a joined/extra table
     * expression (e.g. 't.data').
     */
    public function jsonSource(string $column): static
    {
        $this->dataColumn = $column;

        return $this;
    }

    public function apply(Request $request): static
    {
        $this->search = trim((string) $request->query('search', '')) ?: null;

        if ($this->search !== null && $this->searchable !== []) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->search).'%';
            $this->query->where(function (Builder $q) use ($term): void {
                foreach ($this->searchable as $i => $column) {
                    if (str_starts_with($column, 'data.')) {
                        $path = substr($column, 5);
                        $q->{$i === 0 ? 'where' : 'orWhere'}(
                            fn (Builder $qq) => JsonField::where($qq, $this->dataColumn, $path, 'like', $term, 'string'),
                        );
                    } else {
                        $q->{$i === 0 ? 'where' : 'orWhere'}($column, 'like', $term);
                    }
                }
            });
        }

        foreach ((array) $request->query('filters', []) as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if ($field === 'trashed') {
                match ($value) {
                    'with' => method_exists($this->query->getModel(), 'isForceDeleting')
                        ? $this->query->withTrashed() // @phpstan-ignore-line - SoftDeletingScope macro
                        : $this->query,
                    'only' => method_exists($this->query->getModel(), 'isForceDeleting')
                        ? $this->query->onlyTrashed() // @phpstan-ignore-line - SoftDeletingScope macro
                        : $this->query,
                    default => null,
                };
                $this->activeFilters['trashed'] = $value;

                continue;
            }
            if (! in_array($field, $this->filterable, true)) {
                continue;
            }
            $this->activeFilters[$field] = $value;

            if (array_key_exists($field, $this->jsonColumns)) {
                JsonField::where($this->query, $this->dataColumn, $field, '=', $value, $this->jsonColumns[$field]);
            } elseif ($field === 'terms' || $field === 'term') {
                $ids = is_array($value) ? $value : explode(',', (string) $value);
                $this->query->whereHas('terms', fn (Builder $q) => $q->whereIn('sunrice_terms.id', $ids));
            } else {
                $this->query->where($field, $value);
            }
        }

        $sort = (string) $request->query('sort', '');
        if ($sort !== '') {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $field = ltrim($sort, '-');
            if (in_array($field, $this->sortable, true)) {
                $this->sort = $sort;
                if (array_key_exists($field, $this->jsonColumns)) {
                    JsonField::orderBy($this->query, $this->dataColumn, $field, $this->jsonColumns[$field], $direction);
                } else {
                    $this->query->orderBy($field, $direction);
                }
            }
        }

        return $this;
    }

    /** @return LengthAwarePaginator<int, Model> */
    /** @return LengthAwarePaginator<int, Model> */
    public function paginate(?Request $request = null): LengthAwarePaginator
    {
        $perPage = (int) ($request?->query('per_page', $this->defaultPerPage) ?? $this->defaultPerPage);
        $perPage = max(1, min(100, $perPage));

        return $this->query->paginate($perPage)->withQueryString();
    }

    /** @return Builder<Model> */
    /** @return Builder<Model> */
    public function builder(): Builder
    {
        return $this->query;
    }

    /**
     * @return array{search: ?string, filters: array<string, mixed>, sort: ?string}
     */
    public function meta(): array
    {
        return [
            'search' => $this->search,
            'filters' => $this->activeFilters,
            'sort' => $this->sort,
        ];
    }
}
