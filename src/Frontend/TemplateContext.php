<?php

declare(strict_types=1);

namespace Sunrice\Frontend;

use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;

/**
 * The rendering context passed to template hooks: what kind of page is
 * being rendered and for which content object/locale.
 */
readonly class TemplateContext
{
    public function __construct(
        public string $pageType, // 'entry' | 'archive' | 'term'
        public string $locale,
        public ?Entry $entry = null,
        public ?Collection $collection = null,
        public ?Term $term = null,
        public ?Taxonomy $taxonomy = null,
    ) {}
}
