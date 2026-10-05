<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;

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
}
