<?php

declare(strict_types=1);

namespace Sunrice\Fields;

use InvalidArgumentException;

/**
 * Singleton registry of field types: built-in types plus custom fields
 * registered through service providers.
 */
class FieldRegistry
{
    /** @var array<string, FieldType> */
    protected array $types = [];

    /**
     * @param  class-string<FieldType>  $class
     */
    public function register(string $class): void
    {
        $instance = new $class;
        $this->types[$instance::type()] = $instance;
    }

    public function get(string $type): FieldType
    {
        return $this->types[$type]
            ?? throw new InvalidArgumentException("Unknown field type [{$type}].");
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /**
     * @return array<string, FieldType>
     */
    public function all(): array
    {
        return $this->types;
    }
}
