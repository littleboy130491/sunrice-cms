<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\FormSubmission;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $collections = Collection::query()
            ->withCount('entries')
            ->orderBy('title')
            ->get()
            ->map(fn (Collection $c) => [
                'handle' => $c->handle,
                'title' => $c->title,
                'entries' => $c->entries_count,
            ])
            ->all();

        $recentEdits = Entry::query()
            ->with(['collection:id,title,handle', 'translations'])
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Entry $e) => [
                'id' => $e->id,
                'title' => $e->translations->first()->title ?? '#'.$e->id,
                'collection' => $e->collection?->title,
                'collection_handle' => $e->collection?->handle,
                'status' => $e->status,
                'updated_at' => $e->updated_at?->diffForHumans(),
            ])
            ->all();

        return Inertia::render('Dashboard', [
            'collections' => $collections,
            'recentEdits' => $recentEdits,
            'recentSubmissions' => FormSubmission::query()
                ->with('form:id,title')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (FormSubmission $s) => [
                    'id' => $s->id,
                    'form' => $s->form?->title,
                    'created_at' => $s->created_at?->diffForHumans(),
                ])
                ->all(),
        ]);
    }
}
