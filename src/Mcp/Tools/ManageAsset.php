<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Assets\CreateFolder;
use Sunrice\Actions\Assets\ForceDeleteAsset;
use Sunrice\Actions\Assets\MoveAsset;
use Sunrice\Actions\Assets\RestoreAsset;
use Sunrice\Actions\Assets\TrashAsset;
use Sunrice\Http\Controllers\Admin\AssetsController;
use Sunrice\Mcp\Presenter;
use Sunrice\Models\Asset;
use Sunrice\Models\AssetFolder;

#[Description('Organise the media library. "folders": the folder tree (id, name, depth). "create_folder": name (+ parent_id). "move": put asset `id` in folder_id (null = no folder). "trash" / "restore": move an asset to or from the trash. "delete": delete a trashed asset and its file permanently; refused while content still uses it. Ask the user before deleting.')]
class ManageAsset extends SunriceTool
{
    protected string $name = 'manage_asset';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'action' => ['required', 'in:folders,create_folder,move,trash,restore,delete'],
            'id' => ['required_if:action,move,trash,restore,delete', 'nullable', 'integer'],
            'folder_id' => ['nullable', 'integer', 'exists:sunrice_asset_folders,id'],
            'name' => ['required_if:action,create_folder', 'nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:sunrice_asset_folders,id'],
        ]);

        if ($args['action'] === 'folders') {
            $this->authorize('viewAny', Asset::class);

            return $this->json(['folders' => AssetsController::folderTree()]);
        }
        if ($args['action'] === 'create_folder') {
            $this->authorize('createFolder', Asset::class);
            $folder = app(CreateFolder::class)->handle((string) $args['name'], isset($args['parent_id']) ? (int) $args['parent_id'] : null);

            return $this->json(['created' => true, 'folder' => $folder->only('id', 'parent_id', 'name')]);
        }

        $asset = Asset::withTrashed()->find((int) $args['id']);
        if ($asset === null) {
            return $this->notFound('Asset');
        }

        switch ($args['action']) {
            case 'move':
                $this->authorize('update', $asset);
                $folderId = isset($args['folder_id']) ? (int) $args['folder_id'] : null;
                app(MoveAsset::class)->handle($asset, $folderId);
                $folder = $folderId === null ? null : AssetFolder::query()->find($folderId)?->name;

                return $this->json(['moved' => true, 'folder' => $folder] + Presenter::asset($asset->refresh()));
            case 'trash':
                $this->authorize('delete', $asset);
                app(TrashAsset::class)->handle($asset);

                return $this->json(['trashed' => true, 'id' => $asset->id]);
            case 'restore':
                $this->authorize('delete', $asset);
                app(RestoreAsset::class)->handle($asset);

                return $this->json(['restored' => true] + Presenter::asset($asset->refresh()));
            default:
                $this->authorize('delete', $asset);
                if (! $asset->trashed()) {
                    return Response::error('Trash the asset first (action "trash"); only trashed assets can be deleted permanently.');
                }
                app(ForceDeleteAsset::class)->handle($asset);

                return $this->json(['deleted' => true, 'id' => $asset->id]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['folders', 'create_folder', 'move', 'trash', 'restore', 'delete'])->required(),
            'id' => $schema->integer()->description('Asset id (move, trash, restore, delete).'),
            'folder_id' => $schema->integer()->description('For move; leave out or null for no folder.'),
            'name' => $schema->string()->description('For create_folder.'),
            'parent_id' => $schema->integer()->description('For create_folder: put it inside this folder.'),
        ];
    }
}
