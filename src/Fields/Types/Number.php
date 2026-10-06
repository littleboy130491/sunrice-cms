<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\FieldType;

class Number extends FieldType
{
    public static function type(): string
    {
        return 'number';
    }

    public function rules(array $field): array
    {
        $rules = ['numeric'];
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
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? $value + 0 : null;
    }

    public function sortCast(): ?string
    {
        return 'number';
    }

    public function settingsSchema(): array
    {
        return [
            ['handle' => 'min', 'type' => 'number', 'label' => 'Min'],
            ['handle' => 'max', 'type' => 'number', 'label' => 'Max'],
        ];
    }
}
