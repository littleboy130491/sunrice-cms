<?php

declare(strict_types=1);

namespace Sunrice\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Sunrice\Models\Asset;

/**
 * Generates the configured image sizes for an asset. Each
 * sunrice.assets.image_sizes entry is [width, height, mode]:
 * 'crop' = center cover crop, 'fit' = scale down keeping ratio.
 * Output is written to {path-without-ext}-{name}.{ext} and recorded
 * in the asset's sizes map.
 */
class GenerateImageSizes
{
    public function handle(Asset|int $asset): void
    {
        $asset = $asset instanceof Asset ? $asset : Asset::query()->find($asset);
        if ($asset === null || ! $asset->isImage()) {
            return;
        }
        if (str_contains((string) $asset->mime_type, 'svg')) {
            return;
        }

        $disk = Storage::disk($asset->disk);
        $original = $disk->get($asset->path);
        if ($original === null) {
            return;
        }

        $manager = new ImageManager(extension_loaded('imagick') ? new ImagickDriver : new GdDriver);
        $extension = pathinfo($asset->path, PATHINFO_EXTENSION) ?: 'jpg';
        $base = substr($asset->path, 0, -(strlen($extension) + 1));

        /** @var array<string, array{0: int|null, 1: int|null, 2?: string}> $sizeConfig */
        $sizeConfig = (array) config('sunrice.assets.image_sizes', []);

        $sizes = [];
        foreach ($sizeConfig as $name => [$width, $height, $mode]) {
            try {
                $image = $manager->decode($original);
                if ($mode === 'crop') {
                    $image->cover((int) ($width ?? 0) ?: 1, (int) ($height ?? 0) ?: 1);
                } else {
                    $image->scaleDown($width === null ? null : (int) $width, $height === null ? null : (int) $height);
                }
                $path = $base.'-'.$name.'.'.$extension;
                $disk->put($path, (string) $image->encodeUsingFileExtension($extension));
                $sizes[$name] = $path;
            } catch (\Throwable $e) {
                Log::warning('sunrice: failed generating image size', [
                    'asset' => $asset->id, 'size' => $name, 'error' => $e->getMessage(),
                ]);
            }
        }

        $asset->forceFill(['sizes' => $sizes])->saveQuietly();
    }
}
