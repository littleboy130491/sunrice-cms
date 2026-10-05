<?php

declare(strict_types=1);

namespace Sunrice\Actions\Assets;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Sunrice\Events\ContentChanged;
use Sunrice\Jobs\GenerateImageSizes;
use Sunrice\Models\Asset;

/**
 * Validates size/mime, stores the file on the configured disk under
 * {directory}/{Y}/{m}/{unique-filename}, reads dimensions and
 * dispatches image-size generation.
 */
class UploadAsset
{
    /**
     * @param  array{folder_id?: int|null, title?: string|null, alt?: string|null, uploaded_by?: int|null}  $attributes
     *
     * @throws ValidationException
     */
    public function handle(UploadedFile $file, array $attributes = []): Asset
    {
        Validator::make(
            ['file' => $file],
            ['file' => ['required', 'file', 'max:'.(int) config('sunrice.assets.max_upload_kb', 20480)]],
        )->validate();

        $disk = (string) config('sunrice.assets.disk', 'public');
        $directory = trim((string) config('sunrice.assets.directory', 'sunrice'), '/');

        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $stored = Str::random(8).'-'.($filename !== '' ? $filename : 'file').'.'.$extension;
        $path = $directory.'/'.date('Y/m').'/'.$stored;

        Storage::disk($disk)->put($path, $file->getContent());

        [$width, $height] = $this->dimensions($file);

        $asset = Asset::query()->create([
            'folder_id' => $attributes['folder_id'] ?? null,
            'disk' => $disk,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'width' => $width,
            'height' => $height,
            'title' => $attributes['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'alt' => $attributes['alt'] ?? null,
            'sizes' => [],
            'version' => 1,
            'uploaded_by' => $attributes['uploaded_by'] ?? null,
        ]);

        $this->dispatchSizes($asset);
        ContentChanged::dispatch('asset_uploaded');

        return $asset->refresh();
    }

    /** @return array{0: int|null, 1: int|null} */
    protected function dimensions(UploadedFile $file): array
    {
        $mime = (string) $file->getMimeType();
        if (! str_starts_with($mime, 'image/') || str_contains($mime, 'svg')) {
            return [null, null];
        }

        $info = @getimagesize($file->getRealPath());

        return $info === false ? [null, null] : [(int) $info[0], (int) $info[1]];
    }

    protected function dispatchSizes(Asset $asset): void
    {
        if ($asset->isImage() && ! str_contains((string) $asset->mime_type, 'svg')) {
            app(GenerateImageSizes::class)->handle($asset);
        }
    }
}
