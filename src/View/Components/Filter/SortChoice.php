<?php

declare(strict_types=1);

namespace Sunrice\View\Components\Filter;

/** A sort choice of <x-sunrice::entry-filter>. */
final class SortChoice
{
    public function __construct(
        public string $value,
        public string $label,
        public string $order,
        public bool $selected = false,
    ) {}
}
