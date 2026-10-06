<?php

declare(strict_types=1);

namespace Sunrice\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Sunrice\Models\Entry;

class EntryDeleted
{
    use Dispatchable;

    public function __construct(public readonly Entry $entry) {}
}
