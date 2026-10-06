<?php

declare(strict_types=1);

namespace Sunrice\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired whenever public-facing content changes outside entry
 * publication: term/menu/global/asset saves and deletes, collection
 * and taxonomy structure changes, and homepage setting changes.
 * Listened to by the content-version bumper (T12.2).
 */
class ContentChanged
{
    use Dispatchable;

    public function __construct(public readonly string $reason = '') {}
}
