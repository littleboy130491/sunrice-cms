<?php

declare(strict_types=1);

namespace Sunrice\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires the sunrice.access-admin permission (Super Admin passes
 * via Gate::before).
 */
class EnsureCanAccessAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user(config('sunrice.auth.guard'));

        abort_unless($user !== null && $user->can('sunrice.access-admin'), 403);

        return $next($request);
    }
}
