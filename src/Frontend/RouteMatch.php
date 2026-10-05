<?php

declare(strict_types=1);

namespace Sunrice\Frontend;

use Sunrice\Models\Collection;
use Sunrice\Models\Taxonomy;

/**
 * The result of matching a frontend path against collection/taxonomy
 * route patterns.
 */
readonly class RouteMatch
{
    public function __construct(
        public string $type, // 'entry' | 'archive' | 'term'
        public ?Collection $collection = null,
        public ?Taxonomy $taxonomy = null,
        public ?string $slug = null,
    ) {}
}
