<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sunrice\Models\Asset;

/** JSON endpoint backing the AssetPicker field (T9.3). */
class AssetSearchController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $query = Asset::query()->latest('id');

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(fn ($q) => $q
                ->whereLike('filename', "%{$search}%")
                ->orWhereLike('title', "%{$search}%"));
        }
        if ($request->boolean('images_only')) {
            $query->where('mime_type', 'like', 'image/%');
        }
        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', explode(',', (string) $request->query('ids'))));
        }

        return response()->json([
            'data' => $query->paginate(24)->through(fn (Asset $a): array => [
                'id' => $a->id,
                'filename' => $a->filename,
                'title' => $a->title,
                'mime_type' => $a->mime_type,
                'url' => $a->url(),
                'thumbnail' => $a->url('thumbnail'),
                'is_image' => $a->isImage(),
            ]),
        ]);
    }
}
