<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Frontend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sunrice\Frontend\RouteMatch;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Frontend\TemplateContext;
use Sunrice\Frontend\TemplateResolver;
use Sunrice\Frontend\UrlGenerator;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\Redirect;
use Sunrice\Models\Setting;
use Sunrice\Models\TermTranslation;
use Sunrice\Query\EntryQuery;
use Sunrice\Support\Locales;
use Symfony\Component\HttpFoundation\Response;

class PageController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $path = trim((string) $request->attributes->get('sunrice.path', ''), '/');
        $locale = (string) $request->attributes->get('sunrice.locale', Locales::main());

        if ($path === '') {
            return $this->homepage($locale);
        }

        $match = app(RouteMatcher::class)->match($path);

        if ($match === null) {
            return $this->redirectOr404($path, $locale);
        }

        return match ($match->type) {
            'entry' => $this->entry($match, $path, $locale),
            'archive' => $this->archive($match, $locale),
            'term' => $this->term($match, $path, $locale),
            default => $this->redirectOr404($path, $locale),
        };
    }

    protected function homepage(string $locale): Response
    {
        $entryId = (int) Setting::get('homepage_entry_id', 0);
        $entry = $entryId === 0 ? null : Entry::query()->published()->with('translations')->find($entryId);

        abort_if($entry === null, 404);

        $entry->resolveFor($locale);

        return $this->render(new TemplateContext(
            pageType: 'entry',
            locale: $locale,
            entry: $entry,
            collection: $entry->collection,
        ), ['entry' => $entry, 'collection' => $entry->collection]);
    }

    protected function entry(RouteMatch $match, string $path, string $locale): Response|RedirectResponse
    {
        abort_if($match->collection === null || $match->slug === null, 404);

        // The URL slug may be the locale's Ready translation slug or the
        // main-language slug (fallback pages keep the main-language address).
        // Draft translation slugs are never routable.
        $translation = EntryTranslation::query()
            ->where('collection_id', $match->collection->id)
            ->where('slug', $match->slug)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('locale', $locale)->where('is_ready', true))
                ->orWhere('locale', Locales::main()))
            ->whereHas('entry', fn ($q) => $q->published())
            ->orderByRaw('(locale = ?) desc', [$locale])
            ->first();

        if ($translation === null) {
            return $this->redirectOr404($path, $locale);
        }

        $entry = $translation->entry;
        $entry->resolveFor($locale);

        return $this->render(new TemplateContext(
            pageType: 'entry',
            locale: $locale,
            entry: $entry,
            collection: $match->collection,
        ), ['entry' => $entry, 'collection' => $match->collection]);
    }

    protected function archive(RouteMatch $match, string $locale): Response
    {
        abort_if($match->collection === null || ! $match->collection->setting('has_archive'), 404);

        $entries = EntryQuery::forCollection($match->collection)
            ->locale($locale)
            ->paginate((int) $match->collection->setting('per_page', 12))
            ->withQueryString();

        return $this->render(new TemplateContext(
            pageType: 'archive',
            locale: $locale,
            collection: $match->collection,
        ), [
            'entries' => $entries,
            'collection' => $match->collection,
        ]);
    }

    protected function term(RouteMatch $match, string $path, string $locale): Response|RedirectResponse
    {
        abort_if($match->taxonomy === null || $match->slug === null || ! $match->taxonomy->setting('has_archive'), 404);

        $translation = TermTranslation::query()
            ->where('taxonomy_id', $match->taxonomy->id)
            ->where('slug', $match->slug)
            ->where(fn ($q) => $q->where('locale', $locale)->orWhere('locale', Locales::main()))
            ->whereHas('term', fn ($q) => $q->whereNull('deleted_at'))
            ->orderByRaw('(locale = ?) desc', [$locale])
            ->first();

        if ($translation === null) {
            return $this->redirectOr404($path, $locale);
        }

        $term = $translation->term;
        $term->resolveFor($locale);

        $entries = $term->entries()
            ->published()
            // Per-collection term pages list only that collection's entries.
            ->when($match->collection !== null, fn ($q) => $q->where('collection_id', $match->collection->id))
            ->with('translations')
            ->paginate((int) $match->taxonomy->setting('per_page', 12))
            ->withQueryString();

        // Resolve each entry for the active locale (whole-entry fallback).
        $entries->getCollection()->each(fn (Entry $entry) => $entry->resolveFor($locale));

        return $this->render(new TemplateContext(
            pageType: 'term',
            locale: $locale,
            collection: $match->collection,
            term: $term,
            taxonomy: $match->taxonomy,
        ), [
            'term' => $term,
            'taxonomy' => $match->taxonomy,
            'collection' => $match->collection,
            'entries' => $entries,
        ]);
    }

    protected function redirectOr404(string $path, string $locale): Response|RedirectResponse
    {
        $redirect = Redirect::query()
            ->where('old_path', '/'.$path)
            ->where('locale', $locale)
            ->first();

        if ($redirect !== null) {
            $entry = $redirect->entry()->published()->first();
            if ($entry !== null) {
                return redirect(app(UrlGenerator::class)->entry($entry, $locale), 301);
            }
        }

        abort(404);
    }

    /** @param array<string, mixed> $viewData */
    protected function render(TemplateContext $context, array $viewData): Response
    {
        $view = app(TemplateResolver::class)->resolve($context);

        return response()->view($view, $viewData + [
            'locale' => $context->locale,
            'pageType' => $context->pageType,
        ]);
    }
}
