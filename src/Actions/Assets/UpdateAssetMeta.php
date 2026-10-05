<?php

declare(strict_types=1);

namespace Sunrice\Actions\Assets;

use Sunrice\Events\ContentChanged;
use Sunrice\Models\Asset;

class UpdateAssetMeta
{
    /**
     * @param  array{title?: string|null, alt?: string|null, caption?: string|null}  $attributes
     */
    public function handle(Asset $asset, array $attributes): Asset
    {
        $asset->forceFill([
            'title' => $attributes['title'] ?? $asset->title,
            'alt' => $attributes['alt'] ?? $asset->alt,
            'caption' => $attributes['caption'] ?? $asset->caption,
        ])->save();

        ContentChanged::dispatch('asset_updated');

        return $asset->refresh();
    }
}
