<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;
use Sunrice\Models\Taxonomy;

/**
 * Taxonomy terms. config: taxonomy (handle). Stores term ids; hydrates
 * to terms resolved in the active locale.
 */
class Terms extends FieldType
{
    public static function type(): string
    {
        return 'terms';
    }

    public function rules(array $field): array
    {
        return ['array'];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return collect(is_array($value) ? $value : ($value === null ? [] : [$value]))
            ->map(fn ($id) => (int) $id)->filter()->values()->all();
    }

    public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed
    {
        return $ctx->termsByIds(is_array($value) ? array_map('intval', $value) : []);
    }

    public function references(mixed $value, array $field): array
    {
        return collect(is_array($value) ? $value : ($value === null ? [] : [$value]))
            ->map(fn ($id) => ['target_type' => 'term', 'target_id' => (int) $id])
            ->filter(fn (array $r) => $r['target_id'] > 0)
            ->values()
            ->all();
    }

    public function settingsSchema(): array
    {
        return [
            [
                'handle' => 'taxonomy', 'type' => 'select', 'label' => 'Taxonomy',
                'options' => Taxonomy::query()->orderBy('title')->get(['handle', 'title'])
                    ->map(fn (Taxonomy $t) => ['value' => $t->handle, 'label' => $t->title])->all(),
            ],
        ];
    }
}
