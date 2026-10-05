<?php

declare(strict_types=1);

namespace Sunrice\Cache;

/**
 * Bumps the global content version on any event that changes
 * public-facing content: entry publish/unpublish/delete/restore and
 * ContentChanged (term, menu, global, asset, structure and setting
 * saves). Draft saves never reach these events.
 */
class BumpContentVersion
{
    public function handle(): void
    {
        ContentVersion::bump();
    }
}
