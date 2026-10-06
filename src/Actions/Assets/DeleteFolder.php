<?php

declare(strict_types=1);

namespace Sunrice\Actions\Assets;

use Illuminate\Validation\ValidationException;
use Sunrice\Models\AssetFolder;

class DeleteFolder
{
    /**
     * @throws ValidationException
     */
    public function handle(AssetFolder $folder): void
    {
        if ($folder->children()->exists() || $folder->assets()->exists()) {
            throw ValidationException::withMessages([
                'folder' => ['Only empty folders can be deleted.'],
            ]);
        }

        $folder->delete();
    }
}
