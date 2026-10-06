<?php

declare(strict_types=1);

namespace Sunrice\Actions\Assets;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Asset;

/**
 * Permanently deletes an asset plus its generated size files. Refused
 * while the asset still has usages, unless $force is passed.
 */
class ForceDeleteAsset
{
    /**
     * @throws ValidationException
     */
    public function handle(Asset $asset, bool $force = false): void
    {
        if (! $force && $asset->usages()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'asset' => ['This asset is still in use and cannot be deleted.'],
            ]);
        }

        Storage::disk($asset->disk)->delete($asset->path);
        foreach ($asset->sizes as $path) {
            Storage::disk($asset->disk)->delete($path);
        }
        [$backupDisk, $backupPath] = OptimizeImage::backupLocation($asset);
        $backupDisk->delete($backupPath);

        $asset->forceDelete();
        ContentChanged::dispatch('asset_deleted');
    }
}
