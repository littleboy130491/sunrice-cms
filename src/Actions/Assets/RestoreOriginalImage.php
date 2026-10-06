<?php

declare(strict_types=1);

namespace Sunrice\Actions\Assets;

use Illuminate\Support\Facades\Storage;
use Sunrice\Jobs\GenerateImageSizes;
use Sunrice\Models\Asset;

/**
 * Puts back the original file saved by OptimizeImage, regenerates the
 * image sizes and removes the backup. Returns false when no backup exists.
 */
class RestoreOriginalImage
{
    public function handle(Asset $asset): bool
    {
        [$backupDisk, $backupPath] = OptimizeImage::backupLocation($asset);
        $original = $backupDisk->exists($backupPath) ? $backupDisk->get($backupPath) : null;
        if ($original === null) {
            return false;
        }

        Storage::disk($asset->disk)->put($asset->path, $original);

        $info = @getimagesizefromstring($original);
        $asset->forceFill([
            'size' => strlen($original),
            'width' => $info === false ? $asset->width : (int) $info[0],
            'height' => $info === false ? $asset->height : (int) $info[1],
            'version' => $asset->version + 1,
        ])->save();

        app(GenerateImageSizes::class)->handle($asset);
        $backupDisk->delete($backupPath);

        return true;
    }
}
