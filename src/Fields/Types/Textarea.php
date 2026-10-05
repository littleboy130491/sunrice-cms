<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\FieldType;

class Textarea extends FieldType
{
    public static function type(): string
    {
        return 'textarea';
    }

    public function rules(array $field): array
    {
        return ['string'];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return $value === null ? null : (string) $value;
    }

    public function sortCast(): ?string
    {
        return 'string';
    }
}
