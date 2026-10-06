<?php

declare(strict_types=1);

namespace Sunrice\Actions\Assets;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Sunrice\Events\ContentChanged;
use Sunrice\Jobs\GenerateImageSizes;
use Sunrice\Models\Asset;

/**
 * Overwrites the same storage path (URLs stay valid), bumps the
 * version for cache-busting and regenerates image sizes.
 */
class ReplaceAsset
{
    /**
     * @throws ValidationException
     */
    public function handle(Asset $asset, UploadedFile $file): Asset
    {
        UploadAsset::validate($file);

        // Remove stale generated sizes; the original path is overwritten.
        foreach ($asset->sizes as $path) {
            Storage::disk($asset->disk)->delete($path);
        }

        // Same type: overwrite in place so URLs stay valid. Another type
        // (a JPG replacing a PNG) gets a path with the right extension.
        $path = $asset->path;
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== '' && $extension !== strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            Storage::disk($asset->disk)->delete($path);
            $path = substr($path, 0, -strlen(pathinfo($path, PATHINFO_EXTENSION))).$extension;
        }

        Storage::disk($asset->disk)->put($path, $file->getContent());

        // A backup from sunrice:optimize-images belongs to the replaced file.
        [$backupDisk, $backupPath] = OptimizeImage::backupLocation($asset);
        $backupDisk->delete($backupPath);

        [$width, $height] = [null, null];
        if (str_starts_with((string) $file->getMimeType(), 'image/') && ! str_contains((string) $file->getMimeType(), 'svg')) {
            $info = @getimagesize($file->getRealPath());
            if ($info !== false) {
                [$width, $height] = [(int) $info[0], (int) $info[1]];
            }
        }

        $asset->forceFill([
            'path' => $path,
            'filename' => $file->getClientOriginalName() ?: $asset->filename,
            'mime_type' => $file->getMimeType() ?: $asset->mime_type,
            'size' => $file->getSize() ?: $asset->size,
            'width' => $width,
            'height' => $height,
            'sizes' => [],
            'version' => $asset->version + 1,
        ])->save();

        if ($asset->isImage() && ! str_contains((string) $asset->mime_type, 'svg')) {
            app(GenerateImageSizes::class)->handle($asset);
        }
        ContentChanged::dispatch('asset_replaced');

        return $asset->refresh();
    }
}
