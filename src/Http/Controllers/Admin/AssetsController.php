<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Assets\ForceDeleteAsset;
use Sunrice\Actions\Assets\MoveAsset;
use Sunrice\Actions\Assets\ReplaceAsset;
use Sunrice\Actions\Assets\RestoreAsset;
use Sunrice\Actions\Assets\TrashAsset;
use Sunrice\Actions\Assets\UpdateAssetMeta;
use Sunrice\Actions\Assets\UploadAsset;
use Sunrice\Models\Asset;
use Sunrice\Models\AssetFolder;

class AssetsController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response|JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $query = Asset::query()->with('folder:id,name,path')->latest('id');

        if ($request->filled('folder')) {
            $query->where('folder_id', (int) $request->query('folder'));
        }
        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(fn ($q) => $q
                ->whereLike('filename', "%{$search}%")
                ->orWhereLike('title', "%{$search}%")
                ->orWhereLike('alt', "%{$search}%"));
        }
        if ($request->filled('type')) {
            match ((string) $request->query('type')) {
                'image' => $query->where('mime_type', 'like', 'image/%'),
                'document' => $query->where(fn ($q) => $q
                    ->where('mime_type', 'like', 'application/%')
                    ->orWhere('mime_type', 'like', 'text/%')),
                'video' => $query->where('mime_type', 'like', 'video/%'),
                default => null,
            };
        }
        match ((string) $request->query('trashed', '')) {
            'with' => $query->withTrashed(),
            'only' => $query->onlyTrashed(),
            default => null,
        };

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $query->paginate(24)->through($this->serialize(...)),
                'folders' => AssetFolder::query()->orderBy('name')->get(['id', 'parent_id', 'name']),
            ]);
        }

        return Inertia::render('Assets/Index', [
            'assets' => $query->paginate(24)->through($this->serialize(...)),
            'folders' => AssetFolder::query()->orderBy('name')->get(['id', 'parent_id', 'name']),
            'filters' => $request->only(['folder', 'search', 'type', 'trashed']),
        ]);
    }

    public function show(Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        return response()->json($this->serialize($asset) + ['usages' => $asset->usages()]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Asset::class);

        $validated = $request->validate([
            'file' => ['required', 'file'],
            'folder_id' => ['nullable', 'integer', 'exists:sunrice_asset_folders,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $asset = app(UploadAsset::class)->handle(
            $request->file('file'),
            Arr::except($validated, 'file') + ['uploaded_by' => $request->user()?->id],
        );

        if ($request->wantsJson()) {
            return response()->json($this->serialize($asset), 201);
        }

        return back();
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'folder_id' => ['nullable', 'integer', 'exists:sunrice_asset_folders,id'],
        ]);

        if (array_key_exists('folder_id', $validated)) {
            app(MoveAsset::class)->handle($asset, $validated['folder_id']);
        }
        app(UpdateAssetMeta::class)->handle($asset, $validated);

        return back();
    }

    public function replace(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);
        $request->validate(['file' => ['required', 'file']]);

        app(ReplaceAsset::class)->handle($asset, $request->file('file'));

        return back();
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);
        app(TrashAsset::class)->handle($asset);

        return back();
    }

    public function restore(int $asset): RedirectResponse
    {
        $model = Asset::withTrashed()->findOrFail($asset);
        $this->authorize('delete', $model);
        app(RestoreAsset::class)->handle($model);

        return back();
    }

    public function forceDelete(Request $request, int $asset): RedirectResponse
    {
        $model = Asset::withTrashed()->findOrFail($asset);
        $this->authorize('delete', $model);

        app(ForceDeleteAsset::class)->handle($model, $request->boolean('force'));

        return back();
    }

    /** @return array<string, mixed> */
    protected function serialize(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'filename' => $asset->filename,
            'mime_type' => $asset->mime_type,
            'size' => $asset->size,
            'width' => $asset->width,
            'height' => $asset->height,
            'title' => $asset->title,
            'alt' => $asset->alt,
            'caption' => $asset->caption,
            'folder_id' => $asset->folder_id,
            'url' => $asset->url(),
            'thumbnail' => $asset->url('thumbnail'),
            'sizes' => $asset->sizes,
            'version' => $asset->version,
            'trashed' => $asset->trashed(),
            'created_at' => $asset->created_at?->toIso8601String(),
        ];
    }
}
