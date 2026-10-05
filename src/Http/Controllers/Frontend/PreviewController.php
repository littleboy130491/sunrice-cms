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

        $entry->resolveFor($locale);
        $entry->hydrationContext = new HydrationContext($locale, preview: true);

        // Preview reads draft data rather than live data.
        $resolved = $entry->resolved;
        if ($resolved !== null) {
            $resolved->data = array_merge($resolved->data ?? [], $resolved->draft ?? []);
        }

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
            ])
            ->header('X-Robots-Tag', 'noindex');
    }
}
