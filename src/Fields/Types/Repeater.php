<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Illuminate\Support\Str;
use Sunrice\Fields\BlueprintSchema;
use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;
use Sunrice\Fields\Items;

/**
 * An ordered list of rows sharing the same child fields.
 * config: fields (children), min, max.
 *
 * Each stored row also carries `_id` (stable ULID, used to line up
 * translations), an optional `_key` for templates and `_hidden`.
 * Hydration drops hidden rows and `_hidden`, keeping `_id` and `_key`.
 */
class Repeater extends FieldType
{
    public static function type(): string
    {
        return 'repeater';
    }

    public function translatableByDefault(): bool
    {
        return true;
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

        return array_values(array_map(function ($row) use ($schema) {
            $row = is_array($row) ? $schema->normalize($row) : [];
            $key = trim((string) ($row['_key'] ?? ''));

            $row['_id'] = is_string($row['_id'] ?? null) && $row['_id'] !== '' ? $row['_id'] : (string) Str::ulid();
            $row['_key'] = $key === '' ? null : $key;
            $row['_hidden'] = (bool) ($row['_hidden'] ?? false);

            return $row;
        }, $value));
    }

    public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed
    {
        if (! is_array($value)) {
            return new Items;
        }

        $schema = BlueprintSchema::make($this->children($field));

        return Items::make($value)
            ->filter(fn ($row) => is_array($row) && ! ($row['_hidden'] ?? false))
            ->map(function (array $row) use ($schema, $ctx) {
                unset($row['_hidden']);

                return $schema->hydrate($row, $ctx);
            })
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
                $ref['field_path'] = $i.'.'.($ref['field_path']);
                $refs[] = $ref;
            }
        }

        return $refs;
    }

    public function toAdminSchema(array $field): array
    {
        $field['config']['fields'] = BlueprintSchema::make($this->children($field))->toAdminSchema();
        $field['fields'] = $field['config']['fields'];

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
