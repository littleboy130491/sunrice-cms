<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\FieldType;

/**
 * Single or multiple choice. config: options ([{value,label}] or flat
 * string list), multiple (bool).
 */
class Select extends FieldType
{
    public static function type(): string
    {
        return 'select';
    }

    public function rules(array $field): array
    {
        $options = $this->optionValues($field);

        if ($field['config']['multiple'] ?? false) {
            return ['array'];
        }

        return $options === [] ? ['string'] : ['string', \Illuminate\Validation\Rule::in($options)];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        if ($field['config']['multiple'] ?? false) {
            return is_array($value) ? array_values($value) : ($value === null ? [] : [$value]);
        }

        return $value === null ? null : (string) $value;
    }

    public function sortCast(): ?string
    {
        return 'string';
    }

    /**
     * @return array<int, string>
     */
    protected function optionValues(array $field): array
    {
        return collect($field['config']['options'] ?? [])
            ->map(fn ($o) => is_array($o) ? (string) ($o['value'] ?? '') : (string) $o)
            ->filter(fn (string $v) => $v !== '')
            ->values()
            ->all();
    }

    public function settingsSchema(): array
    {
        return [
            ['handle' => 'multiple', 'type' => 'toggle', 'label' => 'Allow multiple'],
        ];
    }
}
