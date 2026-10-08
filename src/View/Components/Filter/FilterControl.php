<?php

declare(strict_types=1);

namespace Sunrice\View\Components\Filter;

/**
 * One filter of <x-sunrice::entry-filter>, as the form needs it: its
 * label, form field names, current value and options.
 */
final class FilterControl
{
    /** @var array<int, array{value: string, label: string, selected?: bool, count?: int, depth?: int}> */
    public array $options = [];

    /** Current value: a string, a list of strings, a bool (toggle) or ['min' => …, 'max' => …] (ranges). */
    public mixed $value = null;

    /** @var array<string, string> form field names, e.g. ['value' => 'category[]'] or ['min' => 'price_min', 'max' => 'price_max'] */
    public array $inputs;

    /** @var array<int, string> the query-string keys this filter reads */
    public array $params;

    /** @var array<int, string> fields a search filter looks in */
    public array $fields = [];

    /** Taxonomy handle of a terms filter. */
    public ?string $taxonomy = null;

    /** Whether a select filter's field stores a list of values. */
    public bool $storesList = false;

    public function __construct(
        public string $name,
        public string $type,
        public string $field,
        public string $label,
        public bool $multiple,
    ) {
        $this->inputs = ['value' => $name];
        $this->params = [$name];
    }
}
