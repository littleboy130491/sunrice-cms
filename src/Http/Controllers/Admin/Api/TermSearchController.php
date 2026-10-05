<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;

class TermSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $taxonomy = $request->input('taxonomy');
        $q = (string) $request->input('q', '');

        $terms = Term::query()
            ->when($taxonomy, fn ($query) => $query->whereHas('taxonomy', fn ($t) => $t->where('handle', $taxonomy)))
            ->with('translations')
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('translations', fn ($t) => $t->where('name', 'like', "%{$q}%"));
            })
            ->orderBy('sort_order')
            ->limit(50)
            ->get()
            ->map(fn (Term $t) => [
                'id' => $t->id,
                'title' => $t->translations->firstWhere('locale', Locales::main())->name ?? "Term #{$t->id}",
            ])
            ->values();

        return response()->json(['data' => $terms]);
    }
}
