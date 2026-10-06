<?php

declare(strict_types=1);

namespace Sunrice\Fields;

/**
 * Base class for developer custom fields: a PHP class that wraps one
 * built-in field type and adds preset configuration, extra validation
 * rules and an optional value cast — no JavaScript required.
 */
abstract class CustomField extends FieldType
{
    /**
     * The built-in type this field composes (e.g. 'text', 'select').
     */
    abstract public static function baseType(): string;

    /**
     * Preset config merged under the definition's own `config`.
     *
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [];
    }

    /**
     * Extra Laravel validation rules merged after the base type's.
     *
     * @return array<int, string>
     */
    public static function extraRules(): array
    {
        return [];
    }

    protected function base(): FieldType
    {
        return $this->registry()->get(static::baseType());
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    protected function withPreset(array $field): array
    {
        $field['config'] = array_merge(static::config(), $field['config'] ?? []);

        return $field;
    }

    public function rules(array $field): array
    {
        return array_merge($this->base()->rules($this->withPreset($field)), static::extraRules());
    }

    public function defaultValue(array $field): mixed
    {
        return $this->base()->defaultValue($this->withPreset($field));
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return $this->base()->normalize($value, $this->withPreset($field));
    }

    public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed
    {
        return $this->base()->hydrate($value, $this->withPreset($field), $ctx);
    }

    public function references(mixed $value, array $field): array
    {
        return $this->base()->references($value, $this->withPreset($field));
    }

    public function toAdminSchema(array $field): array
    {
        $schema = $this->withPreset($field);
        // The admin renders the base type's component; keep the custom
        // type for display purposes.
        $schema['display_type'] = $schema['type'];
        $schema['type'] = static::baseType();

        return $schema;
    }

    public function translatableByDefault(): bool
    {
        return $this->base()->translatableByDefault();
    }

    public function filterable(): bool
    {
        return $this->base()->filterable();
    }

    public function sortCast(): ?string
    {
        return $this->base()->sortCast();
    }
}
