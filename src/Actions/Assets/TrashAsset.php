<?php

declare(strict_types=1);

namespace Sunrice\Actions\Assets;

use Sunrice\Events\ContentChanged;
use Sunrice\Models\Asset;

class TrashAsset
{
    public function handle(Asset $asset): void
    {
        $asset->delete();
        ContentChanged::dispatch('asset_trashed');
    }
}
