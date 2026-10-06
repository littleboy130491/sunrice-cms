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
        // Only the keys that were sent; an empty value clears the field.
        $asset->forceFill(array_intersect_key($attributes, array_flip(['title', 'alt', 'caption'])))->save();

        ContentChanged::dispatch('asset_updated');

        return $asset->refresh();
    }
}
