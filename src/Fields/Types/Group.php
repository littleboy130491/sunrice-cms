<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\BlueprintSchema;
use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;

/**
 * A named group of fields stored as one object. config.fields holds
 * the child definitions.
 */
class Group extends FieldType
{
    public static function type(): string
    {
        return 'group';
    }

    public function translatableByDefault(): bool
    {
        return true;
    }

    public function rules(array $field): array
    {
        return ['array'];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        if (! is_array($value)) {
            return [];
        }

        return BlueprintSchema::make($this->children($field))->normalize($value);
    }

    public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed
    {
        if (! is_array($value)) {
            return [];
        }

        return BlueprintSchema::make($this->children($field))->hydrate($value, $ctx);
    }

    public function references(mixed $value, array $field): array
    {
        if (! is_array($value)) {
            return [];
        }

        return BlueprintSchema::make($this->children($field))->references($value);
    }

    public function toAdminSchema(array $field): array
    {
        $field['config']['fields'] = BlueprintSchema::make($this->children($field))->toAdminSchema();
        $field['fields'] = $field['config']['fields'];

        return $field;
    }
}
