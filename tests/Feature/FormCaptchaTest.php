<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Actions\Forms\SaveForm;
use Sunrice\Models\Form;
use Sunrice\Models\FormSubmission;
use Sunrice\Support\Captcha;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    config()->set('honeypot.enabled', false);
});

function captchaForm(bool $captcha = true): Form
{
    return app(SaveForm::class)->handle(null, [
        'handle' => 'contact',
        'title' => 'Contact',
        'fields' => [['handle' => 'name', 'type' => 'text', 'required' => true]],
        'settings' => ['captcha' => $captcha],
    ]);
}

function setCaptchaKeys(string $provider = 'turnstile'): void
{
    config()->set('sunrice.forms.captcha', ['provider' => $provider, 'site_key' => 'site-123', 'secret_key' => 'secret-456']);
}

it('is off until both keys are set in .env', function () {
    expect(Captcha::configured())->toBeFalse();

    config()->set('sunrice.forms.captcha.site_key', 'site-123');
    expect(Captcha::configured())->toBeFalse();

    config()->set('sunrice.forms.captcha.secret_key', 'secret-456');
    expect(Captcha::configured())->toBeTrue()
        ->and(Captcha::label())->toBe('Cloudflare Turnstile');

    config()->set('sunrice.forms.captcha.provider', 'unknown');
    expect(Captcha::configured())->toBeFalse();
});

it('ignores the form setting while no keys are set', function () {
    Http::fake();
    $form = captchaForm();

    post('/sunrice/forms/contact', ['data' => ['name' => 'Ada']])->assertRedirect();

    expect(FormSubmission::query()->count())->toBe(1);
    Http::assertNothingSent();
    expect(Blade::render('<x-sunrice::form handle="contact" />'))->not->toContain('cf-turnstile');
});

it('shows the widget and its script once on forms that require it', function () {
    setCaptchaKeys();
    captchaForm();
    app(SaveForm::class)->handle(null, ['handle' => 'newsletter', 'title' => 'Newsletter', 'fields' => [['handle' => 'email', 'type' => 'text']], 'settings' => ['captcha' => true]]);
    app(SaveForm::class)->handle(null, ['handle' => 'plain', 'title' => 'Plain', 'fields' => [['handle' => 'email', 'type' => 'text']]]);

    $html = Blade::render('<x-sunrice::form handle="contact" /><x-sunrice::form handle="newsletter" /><x-sunrice::form handle="plain" />');

    expect(substr_count($html, 'class="sunrice-form-captcha cf-turnstile" data-sitekey="site-123"'))->toBe(2)
        ->and(substr_count($html, 'challenges.cloudflare.com/turnstile/v0/api.js'))->toBe(1)
        ->and($html)->not->toContain('secret-456');
});

it('uses the provider\'s widget class and page language', function (string $provider, string $class, string $script) {
    setCaptchaKeys($provider);
    captchaForm();
    app()->setLocale('en');

    $html = Blade::render('<x-sunrice::form handle="contact" />');

    expect($html)->toContain("sunrice-form-captcha {$class}")->toContain($script);
})->with([
    'reCAPTCHA' => ['recaptcha', 'g-recaptcha', 'https://www.google.com/recaptcha/api.js?hl='],
    'hCaptcha' => ['hcaptcha', 'h-captcha', 'https://js.hcaptcha.com/1/api.js?hl='],
]);

it('accepts a submission whose token the provider confirms', function () {
    setCaptchaKeys();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    captchaForm();

    post('/sunrice/forms/contact', ['data' => ['name' => 'Ada'], 'cf-turnstile-response' => 'token-1'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(FormSubmission::query()->count())->toBe(1);
    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        && $request['secret'] === 'secret-456'
        && $request['response'] === 'token-1'
        && $request['remoteip'] === '127.0.0.1');
});

it('refuses a missing or rejected token, with a message for the form', function () {
    setCaptchaKeys();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);
    captchaForm();

    post('/sunrice/forms/contact', ['data' => ['name' => 'Ada'], '_form' => 'contact'])
        ->assertSessionHasErrors('_captcha');
    Http::assertNothingSent(); // no token: no need to ask

    post('/sunrice/forms/contact', ['data' => ['name' => 'Ada'], '_form' => 'contact', '_locale' => 'en', 'cf-turnstile-response' => 'bad'])
        ->assertSessionHasErrors(['_captcha' => "Please confirm you're not a robot, then send the form again."])
        ->assertSessionMissing('_old_input.cf-turnstile-response');
    expect(FormSubmission::query()->count())->toBe(0);

    // The form shows the message (and keeps the typed values).
    $html = Blade::render('<x-sunrice::form handle="contact"><input name="data[name]" value="{{ $component->old(\'name\') }}"></x-sunrice::form>');
    expect($html)->toContain('Please confirm you&#039;re not a robot')->toContain('value="Ada"');

    post('/sunrice/forms/contact', ['data' => ['name' => 'Ada'], 'cf-turnstile-response' => 'bad'], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['_captcha']]);
});

it('refuses submissions when the provider cannot be reached', function () {
    setCaptchaKeys();
    Http::fake(fn () => throw new ConnectionException('timeout'));
    captchaForm();

    post('/sunrice/forms/contact', ['data' => ['name' => 'Ada'], 'cf-turnstile-response' => 'token'])
        ->assertSessionHasErrors('_captcha');
    expect(FormSubmission::query()->count())->toBe(0);
});

it('leaves forms without the setting alone', function () {
    setCaptchaKeys();
    Http::fake();
    captchaForm(captcha: false);

    post('/sunrice/forms/contact', ['data' => ['name' => 'Ada']])->assertSessionHasNoErrors();

    expect(FormSubmission::query()->count())->toBe(1);
    Http::assertNothingSent();
});

it('tells the form editor whether captcha keys are set', function () {
    actingAsSuperAdmin();
    $form = captchaForm();

    get("/cms/forms/{$form->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('captcha', ['configured' => false, 'provider' => null])
        ->where('form.settings.captcha', true));

    setCaptchaKeys('hcaptcha');
    get('/cms/forms/create')->assertInertia(fn (Assert $page) => $page
        ->where('captcha', ['configured' => true, 'provider' => 'hCaptcha']));
});
