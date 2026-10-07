<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Auth\TwoFactorLogin;
use Throwable;

/** The "enter the code we emailed you" step of two-factor login. */
class TwoFactorController extends Controller
{
    public function __construct(protected TwoFactorLogin $twoFactor) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->twoFactor->user($request);
        if ($user === null) {
            return redirect()->route('sunrice.admin.login');
        }

        return Inertia::render('Auth/TwoFactor', [
            'email' => static::mask((string) data_get($user, 'email')),
            'resendIn' => $this->twoFactor->resendIn($request),
            'minutes' => TwoFactorLogin::LIFETIME_MINUTES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:20']])['code'];

        return match ($this->twoFactor->verify($request, $code)) {
            'ok' => redirect()->intended(route('sunrice.admin.dashboard')),
            'wrong' => throw ValidationException::withMessages(['code' => __('That code isn\'t right. Check the latest email and try again.')]),
            'expired' => redirect()->route('sunrice.admin.login')->with('error', __('The login code expired. Log in again for a new one.')),
            'locked' => redirect()->route('sunrice.admin.login')->with('error', __('Too many wrong codes. Log in again for a new one.')),
            default => redirect()->route('sunrice.admin.login'),
        };
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($this->twoFactor->pending($request) === null) {
            return redirect()->route('sunrice.admin.login');
        }
        if (($wait = $this->twoFactor->resendIn($request)) > 0) {
            return back()->with('error', __('Wait :seconds seconds before asking for another code.', ['seconds' => $wait]));
        }

        try {
            $this->twoFactor->resend($request);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('We couldn\'t email a new code. Ask an administrator to check the mail (SMTP) settings.'));
        }

        return back()->with('success', __('A new code is on its way.'));
    }

    /** j•••@example.com: enough to recognise the inbox without spelling it out. */
    public static function mask(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 1).str_repeat('•', max(2, min(6, mb_strlen($name) - 1))).($domain !== '' ? '@'.$domain : '');
    }
}
