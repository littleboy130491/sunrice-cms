<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\FieldType;

class Toggle extends FieldType
{
    public static function type(): string
    {
        return 'toggle';
    }

    public function rules(array $field): array
    {
        return ['boolean'];
    }

    public function defaultValue(array $field): mixed
    {
        return (bool) ($field['config']['default'] ?? false);
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function sortCast(): ?string
    {
        return 'number';
    }
}
