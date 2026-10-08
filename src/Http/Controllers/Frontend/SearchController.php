<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Frontend;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View;
use Sunrice\Frontend\SiteSearch;
use Sunrice\Support\Locales;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /search?q=… (and /{locale}/search): the site search page, rendered
 * with the site's `sunrice.search` template, else the package default.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, SiteSearch $search): Response
    {
        $query = trim((string) $request->query('q', ''));
        $locale = Locales::current();
        $view = View::exists('sunrice.search') ? 'sunrice.search' : 'sunrice::defaults.search';

        // Search results stay out of search engines.
        $request->attributes->set('sunrice.noindex', true);
        $request->attributes->set('sunrice.template', $view);

        return response()->view($view, [
            'query' => $query,
            'results' => $search->search($query),
            'locale' => $locale,
            'pageType' => 'search',
        ]);
    }
}
