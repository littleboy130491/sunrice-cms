<?php

declare(strict_types=1);

namespace Sunrice\Actions\Assets;

use Illuminate\Validation\ValidationException;
use Sunrice\Models\AssetFolder;

class CreateFolder
{
    /**
     * @throws ValidationException
     */
    public function handle(string $name, ?int $parentId = null): AssetFolder
    {
        if (trim($name) === '') {
            throw ValidationException::withMessages(['name' => ['The folder name is required.']]);
        }

        $parent = $parentId === null ? null : AssetFolder::query()->findOrFail($parentId);

        return AssetFolder::query()->create([
            'parent_id' => $parent?->id,
            'name' => $name,
        ]);
    }
}
