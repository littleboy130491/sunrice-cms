<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;

/**
 * Asset picker. config: multiple (bool), image_only (bool). Stores an
 * asset id or an array of ids; hydrates to Asset models.
 */
class Asset extends FieldType
{
    public static function type(): string
    {
        return 'asset';
    }

    public function rules(array $field): array
    {
        if ($field['config']['multiple'] ?? false) {
            return ['array'];
        }

        return ['integer'];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        if ($field['config']['multiple'] ?? false) {
            return collect(is_array($value) ? $value : ($value === null ? [] : [$value]))
                ->map(fn ($id) => (int) $id)->filter()->values()->all();
        }

        return $value === null ? null : (int) $value;
    }

    public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed
    {
        if ($field['config']['multiple'] ?? false) {
            return $ctx->assetsByIds(is_array($value) ? array_map('intval', $value) : []);
        }

        return $value === null ? null : $ctx->asset((int) $value);
    }

    public function references(mixed $value, array $field): array
    {
        $ids = is_array($value) ? $value : ($value === null ? [] : [$value]);

        return collect($ids)
            ->map(fn ($id) => ['target_type' => 'asset', 'target_id' => (int) $id])
            ->filter(fn (array $r) => $r['target_id'] > 0)
            ->values()
            ->all();
    }

    public function settingsSchema(): array
    {
        return [
            ['handle' => 'multiple', 'type' => 'toggle', 'label' => 'Allow multiple'],
            ['handle' => 'image_only', 'type' => 'toggle', 'label' => 'Images only'],
        ];
    }
}
