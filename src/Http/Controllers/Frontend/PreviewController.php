<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Frontend;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sunrice\Fields\HydrationContext;
use Sunrice\Frontend\TemplateContext;
use Sunrice\Frontend\TemplateResolver;
use Sunrice\Models\Entry;
use Sunrice\Support\Locales;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders an entry straight from its draft payload (live data as
 * fallback) behind a signed URL, ignoring publication status.
 */
class PreviewController extends Controller
{
    public function __invoke(Request $request, Entry $entry, string $locale): Response
    {
        abort_unless(Locales::isAvailable($locale), 404);
        // Menus, globals, dates and interface text in the previewed language.
        app()->setLocale($locale);

        static::applyDraft($entry, $locale);

        $ctx = new TemplateContext(
            pageType: 'entry',
            locale: $locale,
            entry: $entry,
            collection: $entry->collection,
        );

        $view = app(TemplateResolver::class)->resolve($ctx);

        return response()
            ->view($view, [
                'entry' => $entry,
                'collection' => $entry->collection,
                'locale' => $locale,
                'pageType' => 'entry',
                'sunricePage' => $ctx,
            ])
            ->header('X-Robots-Tag', 'noindex');
    }

    /**
     * Resolve the entry for $locale from its drafts (live data as
     * fallback), showing that locale's translation even before it is
     * Ready. A secondary locale is laid over the main language's draft.
     */
    public static function applyDraft(Entry $entry, string $locale): void
    {
        $entry->load('translations');
        $entry->resolveFor($locale);
        $entry->hydrationContext = new HydrationContext($locale, preview: true);

        // Preview shows this locale's translation even before it is Ready,
        // read from the drafts (live data as fallback). A secondary locale
        // is laid over the main language's draft layout.
        $translation = $entry->translation($locale);
        if ($translation !== null) {
            $entry->resolved = $translation;
            $entry->isFallback = false;
        }

        $resolved = $entry->resolved;
        if ($resolved !== null && $resolved->exists) {
            $main = $entry->mainTranslation();
            $entry->dataOverride = $entry->dataFor(
                $resolved,
                (array) ($resolved->draft['data'] ?? $resolved->data ?? []),
                (array) ($main->draft['data'] ?? $main->data ?? []),
            );
            $resolved->title = (string) ($resolved->draft['title'] ?? $resolved->title);
            $resolved->seo = (array) ($resolved->draft['seo'] ?? $resolved->seo ?? []);
        }
    }
}
