<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Collections\SaveListing;
use Sunrice\Frontend\UrlGenerator;
use Sunrice\Models\Collection;
use Sunrice\Support\Locales;

/**
 * The listing page (collection archive) content editor: heading, intro
 * and the fields of the collection's listing blueprint, per language.
 * Editing the main language needs the collection's entry `edit`
 * permission; other languages also accept `translate`.
 */
class ListingController extends Controller
{
    public function edit(Request $request, Collection $collection): Response
    {
        $can = $this->abilities($request, $collection);
        abort_unless($can['edit'] || $can['translate'], 403);

        $schema = $collection->archiveSchema();
        $byLocale = Collection::archiveByLocale($collection->archive_data);
        $urls = app(UrlGenerator::class);

        $values = [];
        foreach (Locales::available() as $locale) {
            $values[$locale] = [
                'title' => $byLocale[$locale]['title'] ?? '',
                'intro' => $byLocale[$locale]['intro'] ?? '',
                // Secondary languages edit their text in the main layout.
                'data' => (object) $collection->archiveFields($locale),
            ];
        }

        return Inertia::render('Collections/Listing', [
            'collection' => [
                'id' => $collection->id,
                'handle' => $collection->handle,
                'title' => $collection->title,
                'has_archive' => (bool) $collection->setting('has_archive', false),
                'urls' => collect(Locales::available())->mapWithKeys(fn (string $l) => [$l => url($urls->archive($collection, $l))]),
            ],
            'fields' => $schema?->toAdminSchema() ?? [],
            'values' => $values,
            'mainLocale' => Locales::main(),
            'can' => $can,
        ]);
    }

    public function update(Request $request, Collection $collection, SaveListing $save): RedirectResponse
    {
        $can = $this->abilities($request, $collection);
        $locale = (string) $request->input('locale', '');
        abort_unless(Locales::isMain($locale) ? $can['edit'] : ($can['edit'] || $can['translate']), 403);

        $save->handle($collection, $request->only(['locale', 'title', 'intro', 'data']));

        return back()->with('success', 'Listing page saved.');
    }

    /** @return array{edit: bool, translate: bool} */
    protected function abilities(Request $request, Collection $collection): array
    {
        $user = $request->user();

        return [
            'edit' => $user->can("sunrice.entries.{$collection->id}.edit"),
            'translate' => $user->can("sunrice.entries.{$collection->id}.translate"),
        ];
    }
}
