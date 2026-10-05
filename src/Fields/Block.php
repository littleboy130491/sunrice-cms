<?php

declare(strict_types=1);

namespace Sunrice\Fields;

use ArrayAccess;

/**
 * One flexible-content block: a fieldset type + hydrated values.
 * Values are reachable as properties and via array access, so Blade
 * can do `$block->type`, `$block->heading`, `$block['image']`.
 *
 * @implements ArrayAccess<string, mixed>
 */
class Block implements ArrayAccess
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly array $values = [],
    ) {}

    public function get(string $handle, mixed $default = null): mixed
    {
        return $this->values[$handle] ?? $default;
    }

    public function __get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->values[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->values[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Block values are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Block values are read-only.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'values' => $this->values];
    }
}
