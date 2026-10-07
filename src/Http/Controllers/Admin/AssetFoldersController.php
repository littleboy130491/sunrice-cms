<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Assets\CreateFolder;
use Sunrice\Actions\Assets\DeleteFolder;
use Sunrice\Actions\Assets\RenameFolder;
use Sunrice\Models\Asset;
use Sunrice\Models\AssetFolder;

class AssetFoldersController extends Controller
{
    use AuthorizesRequests;

    public function create(Request $request): Response
    {
        $this->authorize('createFolder', Asset::class);

        return Inertia::render('Assets/Folder', [
            'folder' => null,
            'parentId' => $request->integer('parent') ?: null,
            'folders' => AssetsController::folderTree(),
        ]);
    }

    public function edit(AssetFolder $folder): Response
    {
        $this->authorize('updateFolder', Asset::class);

        return Inertia::render('Assets/Folder', [
            'folder' => $folder->only('id', 'name', 'parent_id'),
            'parentId' => $folder->parent_id,
            'folders' => AssetsController::folderTree(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('createFolder', Asset::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:sunrice_asset_folders,id'],
        ]);

        $folder = app(CreateFolder::class)->handle($validated['name'], $validated['parent_id'] ?? null);

        return redirect()->route('sunrice.admin.assets.index', ['folder' => $folder->id])->with('success', "Folder \"{$validated['name']}\" created.");
    }

    public function update(Request $request, AssetFolder $folder): RedirectResponse
    {
        $this->authorize('updateFolder', Asset::class);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);
        app(RenameFolder::class)->handle($folder, $validated['name']);

        return redirect()->route('sunrice.admin.assets.index', ['folder' => $folder->id])->with('success', 'Folder renamed.');
    }

    public function destroy(AssetFolder $folder): RedirectResponse
    {
        $this->authorize('deleteFolder', Asset::class);
        app(DeleteFolder::class)->handle($folder);

        return redirect()->route('sunrice.admin.assets.index')->with('success', 'Folder deleted.');
    }
}
