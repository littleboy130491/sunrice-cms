<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Auth\LoginThrottle;
use Sunrice\Auth\TwoFactorLogin;
use Throwable;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request, TwoFactorLogin $twoFactor, LoginThrottle $throttle): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $ip = (string) $request->ip();
        if (($blocked = $throttle->blocked($credentials['email'], $ip)) !== null) {
            throw ValidationException::withMessages([
                'email' => $blocked['seconds'] > 120
                    ? __('Too many login attempts. Try again in :minutes minutes.', ['minutes' => (int) ceil($blocked['seconds'] / 60)])
                    : __('Too many login attempts. Try again in :seconds seconds.', ['seconds' => $blocked['seconds']]),
            ]);
        }

        /** @var SessionGuard $guard */
        $guard = Auth::guard(config('sunrice.auth.guard', 'web'));
        $remember = (bool) ($credentials['remember'] ?? false);
        $login = ['email' => $credentials['email'], 'password' => $credentials['password']];

        // Two-factor: check the password without logging in, then email a
        // code and ask for it on the next screen.
        $passwordOk = TwoFactorLogin::enabled() ? $guard->validate($login) : $guard->attempt($login, $remember);
        if (! $passwordOk) {
            $throttle->failed($credentials['email'], $ip);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $throttle->succeeded($credentials['email'], $ip);

        if (TwoFactorLogin::enabled()) {
            $user = $guard->getLastAttempted();
            if (($seconds = $twoFactor->lockedFor($user)) > 0) {
                throw ValidationException::withMessages([
                    'email' => __('Too many wrong login codes. Try again in :minutes minutes.', ['minutes' => (int) ceil($seconds / 60)]),
                ]);
            }

            try {
                $twoFactor->start($request, $user, $remember);
            } catch (Throwable $e) {
                report($e);

                throw ValidationException::withMessages([
                    'email' => __('We couldn\'t email your login code. Ask an administrator to check the mail (SMTP) settings.'),
                ]);
            }

            return redirect()->route('sunrice.admin.login.code');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('sunrice.admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard(config('sunrice.auth.guard', 'web'))->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('sunrice.admin.login');
    }
}
