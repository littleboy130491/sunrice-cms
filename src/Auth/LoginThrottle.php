<?php

declare(strict_types=1);

namespace Sunrice\Auth;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Brute-force protection for the admin login. Wrong passwords are counted
 * three ways (sunrice.auth.throttle):
 *
 * - the same email from the same IP (a person mistyping, or a script);
 * - one IP across all emails (password spraying);
 * - one account across all IPs (a botnet), which locks the account a while.
 *
 * Failed logins and lockouts are written to the log with email and IP, so
 * attacks show up there (and can feed tools such as fail2ban).
 */
class LoginThrottle
{
    /**
     * Seconds to wait when a limit is reached, and which one.
     *
     * @return array{seconds: int, scope: 'pair'|'ip'|'account'}|null
     */
    public function blocked(string $email, string $ip): ?array
    {
        foreach ($this->limits($email, $ip) as $scope => [$key, $max]) {
            if ($max > 0 && RateLimiter::tooManyAttempts($key, $max)) {
                return ['seconds' => max(1, RateLimiter::availableIn($key)), 'scope' => $scope];
            }
        }

        return null;
    }

    public function failed(string $email, string $ip): void
    {
        $config = (array) config('sunrice.auth.throttle', []);
        foreach ($this->limits($email, $ip) as $scope => [$key, $max]) {
            $decay = match ($scope) {
                'pair' => (int) ($config['decay_seconds'] ?? 60),
                'ip' => (int) ($config['per_ip_decay_seconds'] ?? 60),
                'account' => (int) ($config['account_lock_seconds'] ?? 900),
            };
            RateLimiter::hit($key, $decay);
            if ($max > 0 && RateLimiter::attempts($key) === $max) {
                Log::warning('Sunrice: admin login locked after too many wrong passwords', ['scope' => $scope, 'email' => $email, 'ip' => $ip, 'seconds' => $decay]);
            }
        }

        Log::notice('Sunrice: failed admin login', ['email' => $email, 'ip' => $ip]);
    }

    /** A successful login forgives this email's mistakes (the IP count stays). */
    public function succeeded(string $email, string $ip): void
    {
        $limits = $this->limits($email, $ip);
        RateLimiter::clear($limits['pair'][0]);
        RateLimiter::clear($limits['account'][0]);
    }

    /** @return array{pair: array{0: string, 1: int}, ip: array{0: string, 1: int}, account: array{0: string, 1: int}} */
    protected function limits(string $email, string $ip): array
    {
        $config = (array) config('sunrice.auth.throttle', []);
        $email = mb_strtolower(trim($email));

        return [
            'pair' => ['sunrice-login|'.$email.'|'.$ip, (int) ($config['attempts'] ?? 5)],
            'ip' => ['sunrice-login-ip|'.$ip, (int) ($config['per_ip'] ?? 20)],
            'account' => ['sunrice-login-account|'.$email, (int) ($config['per_account'] ?? 30)],
        ];
    }
}
