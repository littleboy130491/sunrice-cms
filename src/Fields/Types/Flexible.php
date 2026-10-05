<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Illuminate\Support\Str;
use Sunrice\Fields\Block;
use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;
use Sunrice\Models\Fieldset;

/**
 * Flexible content: editors add, repeat and reorder blocks drawn from
 * the fieldsets listed in config.fieldsets. Each block stores
 * {id, type: <fieldset handle>, values: {...}}.
 */
class Flexible extends FieldType
{
    public static function type(): string
    {
        return 'flexible';
    }

    public function rules(array $field): array
    {
        return ['array'];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($block) => is_array($block) && is_array($block['values'] ?? null))
            ->map(function (array $block) {
                $schema = Fieldset::schemaForHandle((string) ($block['type'] ?? ''));

                return [
                    'id' => $block['id'] ?? (string) Str::ulid(),
                    'type' => (string) ($block['type'] ?? ''),
                    // Unknown fieldsets keep their values untouched —
                    // same preserve-hidden-data rule as blueprint switching.
                    'values' => $schema ? $schema->normalize($block['values']) : $block['values'],
                ];
            })
            ->values()
            ->all();
    }

    public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed
    {
        if (! is_array($value)) {
            return collect();
        }

        return collect($value)
            ->filter(fn ($block) => is_array($block) && is_array($block['values'] ?? null))
            ->map(function (array $block) use ($ctx) {
                $schema = Fieldset::schemaForHandle((string) ($block['type'] ?? ''));

                return new Block(
                    type: (string) ($block['type'] ?? ''),
                    id: (string) ($block['id'] ?? ''),
                    values: $schema ? $schema->hydrate($block['values'], $ctx) : $block['values'],
                );
            })
            ->values();
    }

    public function references(mixed $value, array $field): array
    {
        if (! is_array($value)) {
            return [];
        }

        $refs = [];

        foreach ($value as $i => $block) {
            if (! is_array($block) || ! is_array($block['values'] ?? null)) {
                continue;
            }
            $schema = Fieldset::schemaForHandle((string) ($block['type'] ?? ''));
            if ($schema === null) {
                continue;
            }
            foreach ($schema->references($block['values']) as $ref) {
                $ref['field_path'] = $i.'.values.'.($ref['field_path'] ?? '');
                $refs[] = $ref;
            }
        }

        return $refs;
    }
}
