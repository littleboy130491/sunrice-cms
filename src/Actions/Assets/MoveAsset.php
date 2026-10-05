<?php

declare(strict_types=1);

namespace Sunrice\Actions\Assets;

use Sunrice\Events\ContentChanged;
use Sunrice\Models\Asset;

class MoveAsset
{
    public function handle(Asset $asset, ?int $folderId): Asset
    {
        $asset->forceFill(['folder_id' => $folderId])->save();
        ContentChanged::dispatch('asset_moved');

        return $asset->refresh();
    }
}
