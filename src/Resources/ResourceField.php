<?php

declare(strict_types=1);

namespace Sunrice\Resources;

use Illuminate\Support\Str;

/**
 * Fluent description of one editable attribute (or relationship) on a
 * resource's model. `belongs_to` renders a searchable single select of
 * related records, `belongs_to_many` a multi-select; both persist by
 * syncing the relationship.
 */
class ResourceField
{
    /** @var array<string, mixed> */
    protected array $config = [];

    protected ?string $relationship = null;

    protected ?string $relationshipLabelColumn = null;

    protected ?string $label = null;

    protected ?string $type = null;

    protected function __construct(
        public readonly string $attribute,
    ) {}

    public static function make(string $attribute): static
    {
        return new static($attribute); // @phpstan-ignore-line - late static binding intended
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

    /** @param array<string, mixed> $config */
    public function config(array $config): static
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * Mark this field as a relation. `$labelColumn` is the related
     * model's attribute shown in the select (e.g. 'name').
     */
    public function relationship(string $name, string $labelColumn): static
    {
        $this->relationship = $name;
        $this->relationshipLabelColumn = $labelColumn;

        return $this;
    }

    public function relationshipName(): ?string
    {
        return $this->relationship;
    }

    public function relationshipLabelColumn(): ?string
    {
        return $this->relationshipLabelColumn;
    }

    public function isRelationship(): bool
    {
        return $this->relationship !== null;
    }

    /** @return array<string, mixed> */
    public function toAdminField(): array
    {
        return [
            'handle' => $this->attribute,
            'type' => $this->type ?? ($this->relationship !== null ? $this->config['type'] ?? 'text' : 'text'),
            'label' => $this->label ?? Str::headline($this->attribute),
            'config' => $this->config + ($this->relationship !== null ? [
                'relationship' => $this->relationship,
                'label_column' => $this->relationshipLabelColumn,
            ] : []),
        ];
    }
}
