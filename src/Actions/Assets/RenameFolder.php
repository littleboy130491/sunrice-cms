<?php

declare(strict_types=1);

namespace Sunrice\Actions\Assets;

use Illuminate\Validation\ValidationException;
use Sunrice\Models\AssetFolder;

class RenameFolder
{
    /**
     * @throws ValidationException
     */
    public function handle(AssetFolder $folder, string $name): AssetFolder
    {
        if (trim($name) === '') {
            throw ValidationException::withMessages(['name' => ['The folder name is required.']]);
        }

        $folder->forceFill(['name' => $name])->save();

        return $folder->refresh();
    }
}
