<?php

declare(strict_types=1);

namespace Sunrice\Actions\Assets;

use Sunrice\Events\ContentChanged;
use Sunrice\Models\Asset;

class RestoreAsset
{
    public function handle(Asset $asset): Asset
    {
        $asset->restore();
        ContentChanged::dispatch('asset_restored');

        return $asset->refresh();
    }
}
