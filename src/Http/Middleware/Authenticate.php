<?php

declare(strict_types=1);

namespace Sunrice\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;
use Illuminate\Http\Request;

class Authenticate extends BaseAuthenticate
{
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('sunrice.admin.login');
    }

    /**
     * Use the configured Sunrice guard for the admin panel.
     *
     * @param  array<int, string>  $guards
     */
    protected function authenticate($request, array $guards)
    {
        parent::authenticate($request, [config('sunrice.auth.guard', 'web')]);
    }
}
