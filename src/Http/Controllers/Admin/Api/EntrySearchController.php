<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Support\Locales;

class EntrySearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $collections = collect((array) $request->input('collections', []));
        $q = (string) $request->input('q', '');

        $collectionIds = Collection::query()
            ->when($collections->isNotEmpty(), fn ($query) => $query->whereIn('handle', $collections))
            ->pluck('id');

        $entries = Entry::query()
            ->whereIn('collection_id', $collectionIds)
            ->with(['collection:id,handle', 'translations' => fn ($t) => $t->where('locale', Locales::main())])
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('translations', fn ($t) => $t->where('title', 'like', "%{$q}%"));
            })
            ->latest()
            ->limit(25)
            ->get()
            ->filter(fn (Entry $e) => $request->user()?->can('view', $e) ?? true)
            ->map(fn (Entry $e) => [
                'id' => $e->id,
                'title' => $e->translations->first()->title ?? "Entry #{$e->id}",
                'collection' => $e->collection?->handle,
            ])
            ->values();

        return response()->json(['data' => $entries]);
    }
}
