<?php

declare(strict_types=1);

namespace Sunrice\Actions\Assets;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
        Validator::make(
            ['file' => $file],
            ['file' => ['required', 'file', 'max:'.(int) config('sunrice.assets.max_upload_kb', 20480)]],
        )->validate();

        // Remove stale generated sizes; the original path is overwritten.
        foreach ($asset->sizes as $path) {
            Storage::disk($asset->disk)->delete($path);
        }

        Storage::disk($asset->disk)->put($asset->path, $file->getContent());

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
