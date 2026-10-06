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

        $query = Asset::query()->with('folder:id,name')->latest('id');

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
        if ($request->boolean('images')) {
            $query->where('mime_type', 'like', 'image/%');
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
            $page = $query->paginate(min(100, max(1, $request->integer('per_page', 24))))->withQueryString();

            return response()->json([
                'data' => array_map($this->serialize(...), $page->items()),
                'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
                'folders' => static::folderTree(),
            ]);
        }

        return Inertia::render('Assets/Index', [
            'assets' => $query->paginate(24)->withQueryString()->through($this->serialize(...)),
            'folders' => static::folderTree(),
            'filters' => $request->only(['folder', 'search', 'type', 'trashed']),
            'maxUploadKb' => UploadAsset::maxKilobytes(),
            'allowedExtensions' => UploadAsset::allowedExtensions(),
            'storageLinkMissing' => UploadAsset::needsStorageLink(),
        ]);
    }

    public function show(Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        return response()->json($this->serialize($asset) + ['usages' => $asset->usages()]);
    }

    /**
     * Upload one file (`file`) or several (`files[]`) in one request, so
     * a multi-file upload isn't cancelled file by file.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Asset::class);

        $limit = UploadAsset::maxKilobytes();
        $failed = 'A file was larger than this server accepts ('.UploadAsset::formatKilobytes($limit).') or did not finish uploading.';
        $validated = $request->validate([
            'file' => ['required_without:files', 'file'],
            'files' => ['required_without:file', 'array', 'max:50'],
            'files.*' => ['file'],
            'folder_id' => ['nullable', 'integer', 'exists:sunrice_asset_folders,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
        ], [
            'file.uploaded' => $failed,
            'files.*.uploaded' => $failed,
            'file.required_without' => 'Choose a file to upload.',
        ]);

        $files = $request->hasFile('files') ? (array) $request->file('files') : [$request->file('file')];
        $attributes = Arr::except($validated, ['file', 'files']) + ['uploaded_by' => $request->user()?->getAuthIdentifier()];

        $assets = [];
        foreach ($files as $file) {
            $assets[] = app(UploadAsset::class)->handle($file, $attributes);
        }

        if ($request->wantsJson()) {
            return response()->json(count($assets) === 1 ? $this->serialize($assets[0]) : array_map($this->serialize(...), $assets), 201);
        }

        return back()->with('success', count($assets) === 1 ? "Uploaded {$assets[0]->filename}." : 'Uploaded '.count($assets).' files.');
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

        return back()->with('success', 'Asset saved.');
    }

    public function replace(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);
        $request->validate(['file' => ['required', 'file']], [
            'file.uploaded' => 'The file was larger than this server accepts ('.UploadAsset::formatKilobytes(UploadAsset::maxKilobytes()).') or did not finish uploading.',
        ]);

        app(ReplaceAsset::class)->handle($asset, $request->file('file'));

        return back()->with('success', 'File replaced.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);
        app(TrashAsset::class)->handle($asset);

        return back()->with('success', 'Moved to trash.');
    }

    /**
     * Trash several assets in one request.
     */
    public function bulkTrash(Request $request): RedirectResponse
    {
        $validated = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);

        $count = 0;
        foreach (Asset::query()->whereIn('id', $validated['ids'])->get() as $asset) {
            if ($request->user()->can('delete', $asset)) {
                app(TrashAsset::class)->handle($asset);
                $count++;
            }
        }

        return back()->with('success', "Moved {$count} asset(s) to trash.");
    }

    public function restore(int $asset): RedirectResponse
    {
        $model = Asset::withTrashed()->findOrFail($asset);
        $this->authorize('delete', $model);
        app(RestoreAsset::class)->handle($model);

        return back()->with('success', 'Asset restored.');
    }

    public function forceDelete(Request $request, int $asset): RedirectResponse
    {
        $model = Asset::withTrashed()->findOrFail($asset);
        $this->authorize('delete', $model);

        app(ForceDeleteAsset::class)->handle($model, $request->boolean('force'));

        return back()->with('success', 'Asset deleted permanently.');
    }

    /**
     * Folders in tree order with their depth, for indented lists.
     *
     * @return array<int, array{id: int, parent_id: int|null, name: string, depth: int}>
     */
    public static function folderTree(): array
    {
        $all = AssetFolder::query()->orderBy('name')->get(['id', 'parent_id', 'name']);
        $out = [];
        $walk = function (?int $parent, int $depth) use (&$walk, &$out, $all): void {
            foreach ($all->where('parent_id', $parent) as $folder) {
                $out[] = ['id' => $folder->id, 'parent_id' => $folder->parent_id, 'name' => $folder->name, 'depth' => $depth];
                $walk($folder->id, $depth + 1);
            }
        };
        $walk(null, 0);

        return $out;
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
            'is_image' => $asset->isImage(),
            'sizes' => $asset->sizes,
            'version' => $asset->version,
            'trashed' => $asset->trashed(),
            'created_at' => $asset->created_at?->toIso8601String(),
        ];
    }
}
