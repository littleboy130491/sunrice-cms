<?php

declare(strict_types=1);

namespace Sunrice\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sunrice\Models\Form;
use Throwable;

/**
 * Captcha for public forms: Cloudflare Turnstile, Google reCAPTCHA (v2
 * checkbox) or hCaptcha, set up with keys in .env
 * (sunrice.forms.captcha). A form uses it when the keys are set and the
 * form's "captcha" setting is on.
 */
class Captcha
{
    /**
     * Each provider's widget script, widget class, the field its token is
     * posted in, and the address that checks the token. They share the
     * same check API: POST secret + response (+ remoteip) → {success}.
     *
     * @var array<string, array{label: string, script: string, class: string, field: string, verify: string}>
     */
    public const PROVIDERS = [
        'turnstile' => [
            'label' => 'Cloudflare Turnstile',
            'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'class' => 'cf-turnstile',
            'field' => 'cf-turnstile-response',
            'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        ],
        'recaptcha' => [
            'label' => 'Google reCAPTCHA',
            'script' => 'https://www.google.com/recaptcha/api.js',
            'class' => 'g-recaptcha',
            'field' => 'g-recaptcha-response',
            'verify' => 'https://www.google.com/recaptcha/api/siteverify',
        ],
        'hcaptcha' => [
            'label' => 'hCaptcha',
            'script' => 'https://js.hcaptcha.com/1/api.js',
            'class' => 'h-captcha',
            'field' => 'h-captcha-response',
            'verify' => 'https://api.hcaptcha.com/siteverify',
        ],
    ];

    /** The configured provider's key, or null when the keys aren't set. */
    public static function provider(): ?string
    {
        $provider = strtolower((string) config('sunrice.forms.captcha.provider', 'turnstile'));
        $siteKey = (string) config('sunrice.forms.captcha.site_key');
        $secret = (string) config('sunrice.forms.captcha.secret_key');

        return isset(self::PROVIDERS[$provider]) && $siteKey !== '' && $secret !== '' ? $provider : null;
    }

    public static function configured(): bool
    {
        return static::provider() !== null;
    }

    /** Whether submissions of this form must pass the captcha. */
    public static function requiredFor(Form $form): bool
    {
        return static::configured() && (bool) $form->setting('captcha');
    }

    /** The provider's name for the admin ("Cloudflare Turnstile"), or null. */
    public static function label(): ?string
    {
        $provider = static::provider();

        return $provider === null ? null : self::PROVIDERS[$provider]['label'];
    }

    /**
     * What the form view needs to show the widget.
     *
     * @return array{provider: string, class: string, site_key: string, script: string, field: string}|null
     */
    public static function widget(string $locale): ?array
    {
        $provider = static::provider();
        if ($provider === null) {
            return null;
        }
        $def = self::PROVIDERS[$provider];
        // Turnstile takes the language on the widget (data-language); the others on the script.
        $script = $provider === 'turnstile' ? $def['script'] : $def['script'].'?hl='.rawurlencode($locale);

        return [
            'provider' => $provider,
            'class' => $def['class'],
            'site_key' => (string) config('sunrice.forms.captcha.site_key'),
            'script' => $script,
            'field' => $def['field'],
        ];
    }

    /** The request field the provider posts its token in. */
    public static function field(): ?string
    {
        $provider = static::provider();

        return $provider === null ? null : self::PROVIDERS[$provider]['field'];
    }

    /**
     * Ask the provider whether the token is valid. A failed check (bad
     * keys, provider unreachable) refuses the submission rather than
     * letting spam through, and is logged.
     */
    public static function verify(?string $token, ?string $ip = null): bool
    {
        $provider = static::provider();
        if ($provider === null) {
            return true;
        }
        if ($token === null || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::PROVIDERS[$provider]['verify'], array_filter([
                'secret' => (string) config('sunrice.forms.captcha.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (Throwable $e) {
            Log::warning('Sunrice captcha check failed: '.$e->getMessage(), ['provider' => $provider]);

            return false;
        }

        if ($response->json('success') === true) {
            return true;
        }
        $codes = (array) ($response->json('error-codes') ?? []);
        // Configuration problems (not a visitor failing the challenge) are worth a log line.
        if (array_intersect($codes, ['missing-input-secret', 'invalid-input-secret', 'sitekey-secret-mismatch']) !== [] || ! $response->successful()) {
            Log::warning('Sunrice captcha check failed: check SUNRICE_CAPTCHA_SECRET_KEY.', ['provider' => $provider, 'status' => $response->status(), 'errors' => $codes]);
        }

        return false;
    }
}
