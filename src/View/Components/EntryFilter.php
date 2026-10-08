<?php

declare(strict_types=1);

namespace Sunrice\View\Components;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Sunrice\Models\Collection as CollectionModel;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Query\EntryQuery;
use Sunrice\View\Components\Filter\ActiveFilter;
use Sunrice\View\Components\Filter\FilterControl;
use Sunrice\View\Components\Filter\SortChoice;

/**
 * `<x-sunrice::entry-filter>` — a filterable, sortable, paginated list of
 * a collection's entries, driven by the URL (?q=…&category[]=…&sort=…),
 * so results are shareable and work without JavaScript.
 *
 * Renders only its slot. The slot gets:
 *   $component->entries   the current page of entries (paginator)
 *   $component->filters   each filter with its label, input name(s),
 *                         current value and options (for your form)
 *   $component->sorts     the sort choices, one marked selected
 *   $component->active    the applied filters, each with a remove URL
 *   $component->action, ->clearUrl, ->filtered()
 *
 * Only the filters you declare are read from the URL; everything else in
 * the query string is ignored.
 */
class EntryFilter extends Entries
{
    /** @var array<int, FilterControl> */
    public array $filters = [];

    /** @var array<int, SortChoice> */
    public array $sorts = [];

    /** @var array<int, ActiveFilter> */
    public array $active = [];

    public string $action;

    public string $clearUrl;

    /** The query-string name of the sort choice. */
    public string $sortParam;

    /**
     * @param  string  $collection  collection handle
     * @param  array<string, array<string, mixed>|string>  $filters  query name => definition (see docs)
     * @param  array<string, array<string, string>|string>  $sorts  query value => order ("-price") or ['label' => …, 'order' => …]
     * @param  int|null  $perPage  entries per page (default the collection's per_page, else 12)
     * @param  string  $pageName  query-string page name
     * @param  string  $sortName  query-string sort name
     * @param  array<string, mixed>|string  $where  fixed filters, as on <x-sunrice::entries>
     * @param  array<string, string|array<int, string>>|string  $terms  fixed taxonomy filters, as on <x-sunrice::entries>
     * @param  string|null  $with  comma-separated relations to eager load
     */
    public function __construct(
        string $collection,
        array $filters = [],
        array $sorts = [],
        ?int $perPage = null,
        string $pageName = 'page',
        string $sortName = 'sort',
        array|string $where = [],
        array|string $terms = [],
        ?string $with = null,
    ) {
        $model = CollectionModel::query()->where('handle', $collection)->firstOrFail();
        $request = request();
        $query = EntryQuery::forCollection($model);

        foreach ($this->normalizeWheres($where) as [$field, $operator, $value]) {
            $query->where($field, $operator, $value);
        }
        foreach ($this->normalizeTerms($terms) as $taxonomy => $slugs) {
            $query->whereTerm($taxonomy, $slugs, includeChildren: true);
        }

        $this->sortParam = $sortName;
        $owned = [$pageName, $sortName];
        foreach ($filters as $name => $definition) {
            $filter = $this->buildFilter((string) $name, is_array($definition) ? $definition : ['type' => $definition], $model, $request);
            $this->apply($query, $filter);
            $this->filters[] = $filter;
            array_push($owned, ...$filter->params);
        }

        $this->sorts = $this->sortChoices($sorts, (string) $request->query($sortName, ''));
        $sort = collect($this->sorts)->firstWhere('selected', true);
        foreach (array_filter(array_map('trim', explode(',', (string) $sort?->order))) as $order) {
            $query->orderBy(ltrim($order, '-'), str_starts_with($order, '-') ? 'desc' : 'asc');
        }

        if ($with !== null && $with !== '') {
            $query->with(array_map('trim', explode(',', $with)));
        }

        $this->entries = $query->paginate($perPage ?? (int) $model->setting('per_page', 12), $pageName)->withQueryString();

        $this->action = $request->url();
        $others = Arr::except($request->query(), $owned);
        $this->clearUrl = $this->action.($others === [] ? '' : '?'.http_build_query($others));
        $this->active = $this->activeFilters($request, $pageName);
    }

    /** Whether any filter is applied. */
    public function filtered(): bool
    {
        return $this->active !== [];
    }

    /**
     * One declared filter, by its query name.
     */
    public function filter(string $name): ?FilterControl
    {
        return collect($this->filters)->firstWhere('name', $name);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function buildFilter(string $name, array $definition, CollectionModel $collection, Request $request): FilterControl
    {
        $type = (string) ($definition['type'] ?? 'select');
        $field = (string) ($definition['field'] ?? $name);
        $filter = new FilterControl(
            $name,
            $type,
            $field,
            (string) ($definition['label'] ?? Str::headline($name)),
            (bool) ($definition['multiple'] ?? $type === 'terms'),
        );
        $raw = $request->query($name);

        switch ($type) {
            case 'search':
                $filter->fields = array_values(array_map('strval', (array) ($definition['fields'] ?? ['title'])));
                $filter->value = is_string($raw) ? trim(mb_substr($raw, 0, 200)) : '';
                break;

            case 'range':
            case 'date_range':
                $suffixes = $type === 'range' ? ['min', 'max'] : ['from', 'to'];
                $filter->inputs = [$suffixes[0] => $name.'_'.$suffixes[0], $suffixes[1] => $name.'_'.$suffixes[1]];
                $filter->params = array_values($filter->inputs);
                $value = [];
                foreach ($filter->inputs as $key => $input) {
                    $v = $request->query($input);
                    $valid = $type === 'range' ? is_numeric($v) : (is_string($v) && strtotime($v) !== false);
                    $value[$key] = $valid ? (string) $v : null;
                }
                $filter->value = $value;
                break;

            case 'toggle':
                $filter->value = in_array($raw, ['1', 'true', 'on', 'yes'], true);
                break;

            case 'terms':
                $taxonomy = Taxonomy::query()->where('handle', (string) ($definition['taxonomy'] ?? $name))->firstOrFail();
                $filter->taxonomy = $taxonomy->handle;
                $filter->options = $this->termOptions($taxonomy, $collection, (bool) ($definition['hide_empty'] ?? false));
                $filter->value = $this->selected($raw, array_column($filter->options, 'value'), $filter->multiple);
                break;

            default: // select
                $filter->type = 'select';
                $schemaField = $collection->blueprint?->schema()->field($field);
                // A multiple-choice field stores a list of values.
                $filter->storesList = (bool) ($schemaField['config']['multiple'] ?? false);
                $filter->options = $this->selectOptions($definition, $collection, $field);
                $allowed = array_column($filter->options, 'value');
                $filter->value = $this->selected($raw, $allowed, $filter->multiple, $allowed === []);
        }

        if (in_array($filter->type, ['terms', 'select'], true)) {
            $selected = (array) $filter->value;
            $filter->options = array_map(fn (array $o) => $o + ['selected' => in_array($o['value'], $selected, true)], $filter->options);
            if ($filter->multiple) {
                // The form field name: category[] for several values.
                $filter->inputs = ['value' => $name.'[]'];
            }
        }

        return $filter;
    }

    protected function apply(EntryQuery $query, FilterControl $filter): void
    {
        $value = $filter->value;
        switch ($filter->type) {
            case 'search':
                if ($value !== '') {
                    $query->search($value, $filter->fields);
                }
                break;
            case 'range':
                if ($value['min'] !== null) {
                    $query->where($filter->field, '>=', $value['min'] + 0);
                }
                if ($value['max'] !== null) {
                    $query->where($filter->field, '<=', $value['max'] + 0);
                }
                break;
            case 'date_range':
                if ($value['from'] !== null) {
                    $query->where($filter->field, '>=', date('Y-m-d 00:00:00', (int) strtotime($value['from'])));
                }
                if ($value['to'] !== null) {
                    $query->where($filter->field, '<=', date('Y-m-d 23:59:59', (int) strtotime($value['to'])));
                }
                break;
            case 'toggle':
                if ($value) {
                    $query->where($filter->field, true);
                }
                break;
            case 'terms':
                if ($value !== [] && $value !== null) {
                    $query->whereTerm((string) $filter->taxonomy, (array) $value, includeChildren: true);
                }
                break;
            default:
                $this->applySelect($query, $filter);
        }
    }

    protected function applySelect(EntryQuery $query, FilterControl $filter): void
    {
        $values = array_values(array_filter((array) $filter->value, fn ($v) => $v !== '' && $v !== null));
        if ($values === []) {
            return;
        }
        // A list field: entries holding any of the chosen values.
        if ($filter->storesList) {
            $query->where($filter->field, 'contains any', $values);

            return;
        }
        count($values) === 1 ? $query->where($filter->field, $values[0]) : $query->where($filter->field, 'in', $values);
    }

    /**
     * @return array<int, array{value: string, label: string, count: int, depth: int}>
     */
    protected function termOptions(Taxonomy $taxonomy, CollectionModel $collection, bool $hideEmpty): array
    {
        $terms = new Terms($taxonomy->handle, $collection->handle, tree: true, hideEmpty: $hideEmpty);
        $options = [];
        $walk = function ($list, int $depth) use (&$walk, &$options): void {
            foreach ($list as $term) {
                /** @var Term $term */
                $options[] = ['value' => (string) $term->mainTranslation()?->slug, 'label' => (string) $term->name, 'count' => (int) $term->entries_count, 'depth' => $depth];
                $walk($term->children, $depth + 1);
            }
        };
        $walk($terms->terms, 0);

        return array_values(array_filter($options, fn (array $o) => $o['value'] !== ''));
    }

    /**
     * Options given in the definition, else the select field's own options
     * from the collection's blueprint.
     *
     * @param  array<string, mixed>  $definition
     * @return array<int, array{value: string, label: string}>
     */
    protected function selectOptions(array $definition, CollectionModel $collection, string $field): array
    {
        $options = $definition['options'] ?? $collection->blueprint?->schema()->field($field)['config']['options'] ?? [];

        $normalized = [];
        foreach ((array) $options as $key => $option) {
            [$value, $label] = match (true) {
                is_array($option) => [(string) ($option['value'] ?? ''), (string) ($option['label'] ?? $option['value'] ?? '')],
                is_string($key) => [$key, (string) $option],
                default => [(string) $option, (string) $option],
            };
            if ($value !== '') {
                $normalized[] = ['value' => $value, 'label' => $label !== '' ? $label : $value];
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $allowed
     * @return array<int, string>|string|null
     */
    protected function selected(mixed $raw, array $allowed, bool $multiple, bool $anyValue = false): array|string|null
    {
        $values = array_values(array_filter(
            array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', (array) $raw),
            fn (string $v) => $v !== '' && ($anyValue || in_array($v, $allowed, true)),
        ));

        return $multiple ? $values : ($values[0] ?? null);
    }

    /**
     * @param  array<string, array<string, string>|string>  $sorts
     * @return array<int, SortChoice>
     */
    protected function sortChoices(array $sorts, string $current): array
    {
        $choices = [];
        foreach ($sorts as $value => $sort) {
            $order = is_array($sort) ? (string) ($sort['order'] ?? '') : (string) $sort;
            $label = is_array($sort) && isset($sort['label']) ? (string) $sort['label'] : Str::headline((string) $value);
            $choices[] = new SortChoice((string) $value, $label, $order);
        }
        $selected = collect($choices)->firstWhere('value', $current) ?? ($choices[0] ?? null);
        if ($selected !== null) {
            $selected->selected = true;
        }

        return $choices;
    }

    /**
     * The applied filters, each with a link that removes just that value.
     *
     * @return array<int, ActiveFilter>
     */
    protected function activeFilters(Request $request, string $pageName): array
    {
        $query = Arr::except($request->query(), [$pageName]);
        $url = fn (array $params) => $this->action.($params === [] ? '' : '?'.http_build_query($params));
        $active = [];

        foreach ($this->filters as $filter) {
            $labelOf = fn (string $v) => collect($filter->options)->firstWhere('value', $v)['label'] ?? $v;
            switch ($filter->type) {
                case 'search':
                    if ($filter->value !== '') {
                        $active[] = new ActiveFilter($filter->name, $filter->label, (string) $filter->value, $url(Arr::except($query, [$filter->name])));
                    }
                    break;
                case 'range':
                case 'date_range':
                    foreach ($filter->inputs as $key => $input) {
                        if ($filter->value[$key] !== null) {
                            $active[] = new ActiveFilter($filter->name, $filter->label, $key.' '.$filter->value[$key], $url(Arr::except($query, [$input])));
                        }
                    }
                    break;
                case 'toggle':
                    if ($filter->value) {
                        $active[] = new ActiveFilter($filter->name, $filter->label, $filter->label, $url(Arr::except($query, [$filter->name])));
                    }
                    break;
                default:
                    foreach ((array) $filter->value as $value) {
                        // Keeps the other parameters where they were.
                        $rest = $query;
                        $remaining = array_values(array_diff((array) ($query[$filter->name] ?? []), [$value]));
                        if ($filter->multiple && $remaining !== []) {
                            $rest[$filter->name] = $remaining;
                        } else {
                            unset($rest[$filter->name]);
                        }
                        $active[] = new ActiveFilter($filter->name, $filter->label, $labelOf((string) $value), $url($rest));
                    }
            }
        }

        return $active;
    }
}
