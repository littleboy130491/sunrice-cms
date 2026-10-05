<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Illuminate\Support\Carbon;
use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;

/**
 * Stores an ISO 8601 string. config.time = include time of day.
 * Hydrates to a Carbon instance for convenient formatting in Blade.
 */
class Date extends FieldType
{
    public static function type(): string
    {
        return 'date';
    }

    public function rules(array $field): array
    {
        return ['date'];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        return ($field['config']['time'] ?? false)
            ? $date->format('Y-m-d\TH:i:s')
            : $date->format('Y-m-d');
    }

    public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed
    {
        return $value === null ? null : Carbon::parse($value);
    }

    public function sortCast(): ?string
    {
        return 'date';
    }

    public function settingsSchema(): array
    {
        return [
            ['handle' => 'time', 'type' => 'toggle', 'label' => 'Include time'],
        ];
    }
}
