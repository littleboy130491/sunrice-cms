<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\BlueprintSchema;
use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;

/**
 * An ordered list of rows sharing the same child fields.
 * config: fields (children), min, max.
 */
class Repeater extends FieldType
{
    public static function type(): string
    {
        return 'repeater';
    }

    public function rules(array $field): array
    {
        $rules = ['array'];
        if (($field['config']['min'] ?? null) !== null) {
            $rules[] = 'min:'.$field['config']['min'];
        }
        if (($field['config']['max'] ?? null) !== null) {
            $rules[] = 'max:'.$field['config']['max'];
        }

        return $rules;
    }

    public function normalize(mixed $value, array $field): mixed
    {
        if (! is_array($value)) {
            return [];
        }

        $schema = BlueprintSchema::make($this->children($field));

        return array_values(array_map(
            fn ($row) => is_array($row) ? $schema->normalize($row) : [],
            $value,
        ));
    }

    public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed
    {
        if (! is_array($value)) {
            return [];
        }

        $schema = BlueprintSchema::make($this->children($field));

        return collect($value)
            ->map(fn ($row) => is_array($row) ? $schema->hydrate($row, $ctx) : [])
            ->values();
    }

    public function references(mixed $value, array $field): array
    {
        if (! is_array($value)) {
            return [];
        }

        $schema = BlueprintSchema::make($this->children($field));
        $refs = [];

        foreach ($value as $i => $row) {
            foreach ($schema->references(is_array($row) ? $row : []) as $ref) {
                $ref['field_path'] = $i.'.'.($ref['field_path'] ?? '');
                $refs[] = $ref;
            }
        }

        return $refs;
    }

    public function toAdminSchema(array $field): array
    {
        $field['config']['fields'] = BlueprintSchema::make($this->children($field))->toAdminSchema();

        return $field;
    }

    public function settingsSchema(): array
    {
        return [
            ['handle' => 'min', 'type' => 'number', 'label' => 'Minimum rows'],
            ['handle' => 'max', 'type' => 'number', 'label' => 'Maximum rows'],
        ];
    }
}
