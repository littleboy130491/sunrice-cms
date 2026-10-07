<?php

declare(strict_types=1);

namespace Sunrice\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Sunrice\Notifications\LoginCode;

/**
 * Two-factor login by email: after a correct password the user isn't
 * logged in yet. A 6-digit code is emailed, and the pending login waits in
 * the session until the code is entered.
 *
 * Only a hash of the code is stored. Each code expires after
 * LIFETIME_MINUTES and allows MAX_ATTEMPTS guesses. Wrong codes also count
 * per user across logins (LOCKOUT_*), so logging in again for a fresh code
 * doesn't give unlimited guesses.
 */
class TwoFactorLogin
{
    public const SESSION_KEY = 'sunrice_two_factor';

    public const LIFETIME_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public const LOCKOUT_ATTEMPTS = 10;

    public const LOCKOUT_SECONDS = 900;

    public static function enabled(): bool
    {
        return (bool) config('sunrice.auth.two_factor', false);
    }

    /** Seconds until this user may try again, or 0 when not locked out. */
    public function lockedFor(Authenticatable $user): int
    {
        $key = $this->lockoutKey($user->getAuthIdentifier());

        return RateLimiter::tooManyAttempts($key, static::LOCKOUT_ATTEMPTS) ? RateLimiter::availableIn($key) : 0;
    }

    /**
     * Start a pending login and email its code. Nothing is stored when
     * sending fails, so the caller can report the error.
     */
    public function start(Request $request, Authenticatable $user, bool $remember): void
    {
        $this->send($request, $user, ['user_id' => $user->getAuthIdentifier(), 'remember' => $remember]);
    }

    /** Email a new code for the pending login (the old one stops working). */
    public function resend(Request $request): void
    {
        $pending = $this->pending($request);
        $user = $this->user($request);
        if ($pending !== null && $user !== null) {
            $this->send($request, $user, $pending);
        }
    }

    /**
     * The pending login: user_id, remember, code (hash), expires_at,
     * sent_at, attempts.
     *
     * @return array<mixed>|null
     */
    public function pending(Request $request): ?array
    {
        $pending = $request->session()->get(static::SESSION_KEY);

        return is_array($pending) && isset($pending['user_id'], $pending['code']) ? $pending : null;
    }

    public function user(Request $request): ?Authenticatable
    {
        $pending = $this->pending($request);

        return $pending === null ? null : $this->guard()->getProvider()->retrieveById($pending['user_id']);
    }

    /** Seconds until another code may be sent. */
    public function resendIn(Request $request): int
    {
        $pending = $this->pending($request);

        return $pending === null ? 0 : max(0, (int) $pending['sent_at'] + static::RESEND_SECONDS - now()->getTimestamp());
    }

    /**
     * Check a code. On success the pending login is completed.
     *
     * @return 'ok'|'wrong'|'expired'|'locked'|'missing'
     */
    public function verify(Request $request, string $code): string
    {
        $pending = $this->pending($request);
        $user = $this->user($request);
        if ($pending === null || $user === null) {
            return 'missing';
        }
        if ((int) $pending['expires_at'] < now()->getTimestamp()) {
            $this->forget($request);

            return 'expired';
        }

        if (! hash_equals((string) $pending['code'], $this->hash(preg_replace('/\D/', '', $code) ?? ''))) {
            RateLimiter::hit($this->lockoutKey($pending['user_id']), static::LOCKOUT_SECONDS);
            $pending['attempts'] = (int) $pending['attempts'] + 1;
            if ($pending['attempts'] >= static::MAX_ATTEMPTS || $this->lockedFor($user) > 0) {
                $this->forget($request);

                return 'locked';
            }
            $request->session()->put(static::SESSION_KEY, $pending);

            return 'wrong';
        }

        $this->forget($request);
        RateLimiter::clear($this->lockoutKey($pending['user_id']));
        $this->guard()->login($user, (bool) $pending['remember']);
        $request->session()->regenerate();

        return 'ok';
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(static::SESSION_KEY);
    }

    /**
     * @param  array<mixed>  $pending
     */
    protected function send(Request $request, Authenticatable $user, array $pending): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // LoginCode isn't queued: this throws when the mail can't be sent.
        Notification::sendNow($user, new LoginCode($code));

        $request->session()->put(static::SESSION_KEY, [
            'user_id' => $pending['user_id'],
            'remember' => (bool) $pending['remember'],
            'code' => $this->hash($code),
            'expires_at' => now()->getTimestamp() + static::LIFETIME_MINUTES * 60,
            'sent_at' => now()->getTimestamp(),
            'attempts' => 0,
        ]);
    }

    protected function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    protected function lockoutKey(mixed $userId): string
    {
        return 'sunrice-two-factor|'.$userId;
    }

    protected function guard(): SessionGuard
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard(config('sunrice.auth.guard', 'web'));

        return $guard;
    }
}
