<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sunrice\Actions\Assets\CreateFolder;
use Sunrice\Actions\Assets\DeleteFolder;
use Sunrice\Actions\Assets\RenameFolder;
use Sunrice\Models\Asset;
use Sunrice\Models\AssetFolder;

class AssetFoldersController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Asset::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:sunrice_asset_folders,id'],
        ]);

        app(CreateFolder::class)->handle($validated['name'], $validated['parent_id'] ?? null);

        return back();
    }

    public function update(Request $request, AssetFolder $folder): RedirectResponse
    {
        $this->authorize('create', Asset::class);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);
        app(RenameFolder::class)->handle($folder, $validated['name']);

        return back();
    }

    public function destroy(AssetFolder $folder): RedirectResponse
    {
        $this->authorize('delete', Asset::class);
        app(DeleteFolder::class)->handle($folder);

        return back();
    }
}
