<?php

declare(strict_types=1);

namespace Sunrice\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sunrice\Support\Locales;
use Symfony\Component\HttpFoundation\Response;

/**
 * Detects the frontend locale from the first path segment. A non-main
 * available locale prefix selects that locale and is stripped for
 * route matching (exposed as the `sunrice.path` request attribute);
 * anything else selects the main locale.
 */
class SetFrontendLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');
        $segment = explode('/', $path)[0];

        $locale = Locales::main();
        $stripped = $path;

        if ($segment !== '' && ! Locales::isMain($segment) && Locales::isAvailable($segment)) {
            $locale = $segment;
            $stripped = trim(substr($path, strlen($segment)), '/');
        }

        app()->setLocale($locale);
        $request->attributes->set('sunrice.locale', $locale);
        $request->attributes->set('sunrice.path', $stripped);

        return $next($request);
    }
}
