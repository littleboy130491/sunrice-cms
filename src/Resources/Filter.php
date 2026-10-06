<?php

declare(strict_types=1);

namespace Sunrice\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * An index-table filter over a model column.
 */
class Filter
{
    /** @var array<string, string> */
    protected array $options = [];

    protected string $type = 'select';

    protected ?string $label = null;

    protected function __construct(
        public readonly string $column,
    ) {}

    public static function make(string $column): static
    {
        return new static($column); // @phpstan-ignore-line - late static binding intended
    }

    public static function select(string $column): static
    {
        return (new static($column))->type('select'); // @phpstan-ignore-line
    }

    public static function boolean(string $column): static
    {
        return (new static($column))->type('boolean'); // @phpstan-ignore-line
    }

    public static function dateRange(string $column): static
    {
        return (new static($column))->type('date_range'); // @phpstan-ignore-line
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /** @param array<string, string> $options */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Apply a raw request value to the query.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, mixed $value): Builder
    {
        return match ($this->type) {
            'boolean' => $query->where($this->column, filter_var($value, FILTER_VALIDATE_BOOLEAN)),
            'date_range' => $query
                ->when($value['from'] ?? null, fn (Builder $q, $from) => $q->whereDate($this->column, '>=', $from))
                ->when($value['to'] ?? null, fn (Builder $q, $to) => $q->whereDate($this->column, '<=', $to)),
            default => $query->where($this->column, $value),
        };
    }

    /** @return array<string, mixed> */
    public function toMeta(): array
    {
        return [
            'key' => $this->column,
            'label' => $this->label ?? Str::headline($this->column),
            'type' => $this->type,
            'options' => $this->options,
        ];
    }
}
