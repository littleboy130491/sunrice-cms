<?php

declare(strict_types=1);

namespace Sunrice\Frontend;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * A resolved navigation item for Blade rendering.
 *
 * @implements Arrayable<string, mixed>
 */
class MenuNode implements Arrayable
{
    /**
     * @param  Collection<int, MenuNode>  $children
     */
    public function __construct(
        public readonly string $label,
        public readonly string $url,
        public readonly bool $newTab = false,
        public readonly bool $isActive = false,
        public readonly Collection $children = new Collection,
    ) {}

    public function isActiveOrAncestor(): bool
    {
        if ($this->isActive) {
            return true;
        }

        return $this->children->contains(fn (MenuNode $child) => $child->isActiveOrAncestor());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'url' => $this->url,
            'new_tab' => $this->newTab,
            'is_active' => $this->isActive,
            'children' => $this->children->map->toArray()->all(),
        ];
    }

    /** @param array<string, mixed> $node as from toArray() */
    public static function fromArray(array $node): self
    {
        return new self(
            label: (string) ($node['label'] ?? ''),
            url: (string) ($node['url'] ?? ''),
            newTab: (bool) ($node['new_tab'] ?? false),
            isActive: (bool) ($node['is_active'] ?? false),
            children: (new Collection((array) ($node['children'] ?? [])))->map(fn (array $child) => self::fromArray($child))->values(),
        );
    }
}
