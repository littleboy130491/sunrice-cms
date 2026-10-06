<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\FieldType;

class Text extends FieldType
{
    public static function type(): string
    {
        return 'text';
    }

    public function translatableByDefault(): bool
    {
        return true;
    }

    public function rules(array $field): array
    {
        $rules = ['string'];
        if ($max = $field['config']['max'] ?? null) {
            $rules[] = 'max:'.$max;
        }

        return $rules;
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return $value === null ? null : (string) $value;
    }

    public function sortCast(): ?string
    {
        return 'string';
    }

    public function settingsSchema(): array
    {
        return [
            ['handle' => 'max', 'type' => 'number', 'label' => 'Max characters'],
            ['handle' => 'default', 'type' => 'text', 'label' => 'Default value'],
        ];
    }
}
