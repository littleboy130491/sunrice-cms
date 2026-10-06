<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;
use Sunrice\Models\Collection;

/**
 * Relationship to other entries. config: collections (array of
 * handles), max (int). Stores entry ids; hydrates to entries resolved
 * in the active locale.
 */
class Entries extends FieldType
{
    public static function type(): string
    {
        return 'entries';
    }

    public function rules(array $field): array
    {
        $rules = ['array'];
        if ($max = $field['config']['max'] ?? null) {
            $rules[] = 'max:'.$max;
        }

        return $rules;
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return collect(is_array($value) ? $value : ($value === null ? [] : [$value]))
            ->map(fn ($id) => (int) $id)->filter()->values()->all();
    }

    public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed
    {
        return $ctx->entriesByIds(is_array($value) ? array_map('intval', $value) : []);
    }

    public function references(mixed $value, array $field): array
    {
        return collect(is_array($value) ? $value : ($value === null ? [] : [$value]))
            ->map(fn ($id) => ['target_type' => 'entry', 'target_id' => (int) $id])
            ->filter(fn (array $r) => $r['target_id'] > 0)
            ->values()
            ->all();
    }

    public function settingsSchema(): array
    {
        return [
            [
                'handle' => 'collections', 'type' => 'multiselect', 'label' => 'Collections (none = any)',
                'options' => Collection::query()->orderBy('title')->get(['handle', 'title'])
                    ->map(fn (Collection $c) => ['value' => $c->handle, 'label' => $c->title])->all(),
            ],
            ['handle' => 'max', 'type' => 'number', 'label' => 'Maximum entries'],
        ];
    }
}
