<?php

declare(strict_types=1);

namespace Sunrice\Actions\Assets;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Sunrice\Jobs\GenerateImageSizes;
use Sunrice\Models\Asset;

/**
 * Shrinks an image asset to fit within a maximum width/height and
 * re-encodes it (JPEG, WebP and AVIF with the given quality; PNG
 * losslessly). The file is overwritten in place so its URL and every
 * content reference stay valid. Unless disabled, the original is copied
 * to the backup location first; an existing backup is never overwritten,
 * so it always holds the file as first uploaded.
 *
 * Returns ['status' => 'optimized'|'skipped', 'reason' => ?string,
 * 'before' => bytes, 'after' => bytes, 'width' => ?int, 'height' => ?int].
 */
class OptimizeImage
{
    /** Formats this action can re-encode. GIFs are skipped to keep animations intact. */
    protected const SUPPORTED = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

    protected const LOSSY = ['jpg', 'jpeg', 'webp', 'avif'];

    /**
     * @return array{status: string, reason: ?string, before: int, after: int, width: ?int, height: ?int}
     */
    public function handle(
        Asset $asset,
        int $maxWidth,
        int $maxHeight,
        int $quality,
        bool $backup = true,
        bool $dryRun = false,
    ): array {
        $extension = strtolower(pathinfo($asset->path, PATHINFO_EXTENSION));
        $result = ['status' => 'skipped', 'reason' => null, 'before' => (int) $asset->size, 'after' => (int) $asset->size, 'width' => $asset->width, 'height' => $asset->height];

        if (! $asset->isImage() || ! in_array($extension, self::SUPPORTED, true)) {
            return ['reason' => 'unsupported format'] + $result;
        }

        $disk = Storage::disk($asset->disk);
        $original = $disk->get($asset->path);
        if ($original === null) {
            return ['reason' => 'file missing'] + $result;
        }

        $image = (new ImageManager(extension_loaded('imagick') ? new ImagickDriver : new GdDriver))->decode($original);
        $needsResize = $image->width() > $maxWidth || $image->height() > $maxHeight;
        if ($needsResize) {
            $image->scaleDown($maxWidth, $maxHeight);
        }

        $encoded = in_array($extension, self::LOSSY, true)
            ? (string) $image->encodeUsingFileExtension($extension, quality: $quality)
            : (string) $image->encodeUsingFileExtension($extension);

        $result['before'] = strlen($original);

        // Keep the original when re-encoding would not make it smaller and it already fits.
        if (! $needsResize && strlen($encoded) >= strlen($original)) {
            return ['reason' => 'already optimized'] + $result;
        }

        $result = [
            'status' => 'optimized',
            'reason' => null,
            'before' => strlen($original),
            'after' => strlen($encoded),
            'width' => $image->width(),
            'height' => $image->height(),
        ];

        if ($dryRun) {
            return $result;
        }

        if ($backup) {
            [$backupDisk, $backupPath] = static::backupLocation($asset);
            if (! $backupDisk->exists($backupPath)) {
                $backupDisk->put($backupPath, $original);
            }
        }

        $disk->put($asset->path, $encoded);
        $asset->forceFill([
            'size' => $result['after'],
            'width' => $result['width'],
            'height' => $result['height'],
            'version' => $asset->version + 1,
        ])->save();

        app(GenerateImageSizes::class)->handle($asset);

        return $result;
    }

    /**
     * Where the original of an asset is kept: [disk, path].
     *
     * @return array{0: Filesystem, 1: string}
     */
    public static function backupLocation(Asset $asset): array
    {
        $disk = config('sunrice.assets.optimize.backup_disk') ?: $asset->disk;
        $directory = trim((string) config('sunrice.assets.optimize.backup_directory', 'sunrice-originals'), '/');

        return [Storage::disk($disk), $directory.'/'.ltrim($asset->path, '/')];
    }
}
