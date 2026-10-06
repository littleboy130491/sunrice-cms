<?php

declare(strict_types=1);

namespace Sunrice\Frontend;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Hydrated values of one global set for a resolved locale. Field
 * values are accessed as properties or via get(); unknown keys
 * normalize through the blueprint like entry data.
 *
 * @implements Arrayable<string, mixed>
 * @implements \ArrayAccess<string, mixed>
 */
class GlobalData implements \ArrayAccess, Arrayable
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        public readonly string $handle,
        public readonly string $locale,
        protected array $values = [],
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function __isset(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->values[(string) $offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->values[(string) $offset]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values;
    }
}
