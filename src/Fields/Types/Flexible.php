<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Illuminate\Support\Str;
use Sunrice\Fields\Block;
use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;
use Sunrice\Fields\Items;
use Sunrice\Models\Fieldset;

/**
 * Flexible content: editors add, repeat and reorder blocks drawn from
 * the fieldsets listed in config.fieldsets. Each block stores
 * {id, type: <fieldset handle>, key, hidden, values: {...}}. `key` is an
 * optional handle for templates; hidden blocks are skipped on the site.
 */
class Flexible extends FieldType
{
    public static function type(): string
    {
        return 'flexible';
    }

    public function translatableByDefault(): bool
    {
        return true;
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
                $key = trim((string) ($block['key'] ?? ''));

                return [
                    'id' => is_string($block['id'] ?? null) && $block['id'] !== '' ? $block['id'] : (string) Str::ulid(),
                    'type' => (string) ($block['type'] ?? ''),
                    'key' => $key === '' ? null : $key,
                    'hidden' => (bool) ($block['hidden'] ?? false),
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
            return new Items;
        }

        return Items::make($value)
            ->filter(fn ($block) => is_array($block) && is_array($block['values'] ?? null) && ! ($block['hidden'] ?? false))
            ->map(function (array $block) use ($ctx) {
                $schema = Fieldset::schemaForHandle((string) ($block['type'] ?? ''));

                return new Block(
                    type: (string) ($block['type'] ?? ''),
                    id: (string) ($block['id'] ?? ''),
                    values: $schema ? $schema->hydrate($block['values'], $ctx) : $block['values'],
                    key: is_string($block['key'] ?? null) ? $block['key'] : null,
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
                $ref['field_path'] = $i.'.values.'.($ref['field_path']);
                $refs[] = $ref;
            }
        }

        return $refs;
    }

    public function toAdminSchema(array $field): array
    {
        // The editor needs each allowed fieldset's fields to render blocks
        // (no list = every fieldset). The depth guard stops a fieldset that
        // contains a flexible field allowing itself from recursing forever.
        static $depth = 0;
        if ($depth > 3) {
            $field['fieldsets'] = [];

            return $field;
        }

        $allowed = array_values((array) ($field['config']['fieldsets'] ?? []));
        $depth++;

        try {
            $field['fieldsets'] = $this->allowedFieldsets($allowed);
        } finally {
            $depth--;
        }

        return $field;
    }

    /**
     * @param  array<int, mixed>  $allowed
     * @return array<int, array{handle: string, title: string, fields: array<int, array<string, mixed>>}>
     */
    protected function allowedFieldsets(array $allowed): array
    {
        return Fieldset::query()
            ->when($allowed !== [], fn ($q) => $q->whereIn('handle', $allowed))
            ->orderBy('title')
            ->get()
            ->map(fn (Fieldset $fieldset) => [
                'handle' => $fieldset->handle,
                'title' => $fieldset->title,
                'fields' => $fieldset->schema()->toAdminSchema(),
            ])
            ->values()
            ->all();
    }

    public function settingsSchema(): array
    {
        return [
            ['handle' => 'fieldsets', 'type' => 'handles', 'label' => 'Allowed block types (fieldsets)'],
        ];
    }
}
