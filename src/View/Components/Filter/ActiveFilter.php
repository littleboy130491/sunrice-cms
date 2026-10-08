<?php

declare(strict_types=1);

namespace Sunrice\View\Components\Filter;

/** An applied filter value of <x-sunrice::entry-filter>, with a link that removes it. */
final class ActiveFilter
{
    public function __construct(
        public string $filter,
        public string $label,
        public string $value,
        public string $remove_url,
    ) {}
}
