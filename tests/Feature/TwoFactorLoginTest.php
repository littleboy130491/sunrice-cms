<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Sunrice\Auth\TwoFactorLogin;
use Sunrice\Models\Setting;
use Sunrice\Notifications\LoginCode;
use Sunrice\Notifications\TestMail;
use Sunrice\Permissions\SyncPermissions;
use Sunrice\Support\SiteSettings;
use Workbench\App\Models\User;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/** Mail that can't be delivered: nothing listens on port 1. */
function brokenMail(): void
{
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 2],
    ]);
}

/** The code in the last login email to this user. */
function lastLoginCode(User $user): string
{
    /** @var NotificationFake $fake */
    $fake = Notification::getFacadeRoot();

    return $fake->sent($user, LoginCode::class)->last()->code;
}

function resetSiteSettings(): void
{
    Setting::set('site', []);
    Cache::forget(SiteSettings::CACHE_KEY);
}

beforeEach(function () {
    resetSiteSettings();
    $this->user = User::create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => Hash::make('secret-pw')]);
    app(SyncPermissions::class)->handle();
    $role = Role::findOrCreate('member', 'web');
    $role->givePermissionTo('sunrice.access-admin');
    $this->user->assignRole($role);
    RateLimiter::clear('sunrice-two-factor|'.$this->user->id);
});

afterEach(fn () => resetSiteSettings());

it('logs straight in when two-factor is off (the default)', function () {
    Notification::fake();
    expect(TwoFactorLogin::enabled())->toBeFalse();

    post('/cms/login', ['email' => 'rina@example.com', 'password' => 'secret-pw'])->assertRedirect('/cms');

    expect(Auth::check())->toBeTrue();
    Notification::assertNothingSent();
});

it('asks for an emailed code after the password when two-factor is on', function () {
    Notification::fake();
    config(['sunrice.auth.two_factor' => true]);

    post('/cms/login', ['email' => 'rina@example.com', 'password' => 'secret-pw'])->assertRedirect('/cms/login/code');
    expect(Auth::check())->toBeFalse();
    Notification::assertSentTo($this->user, LoginCode::class);

    get('/cms/login/code')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Auth/TwoFactor')
        ->where('email', 'r•••@example.com'));
    // Not logged in yet: the admin still sends you to the login page.
    get('/cms')->assertRedirect(route('sunrice.admin.login'));

    $code = lastLoginCode($this->user);
    $wrong = $code === '000000' ? '111111' : '000000';
    post('/cms/login/code', ['code' => $wrong])->assertSessionHasErrors('code');
    expect(Auth::check())->toBeFalse();

    post('/cms/login/code', ['code' => $code])->assertRedirect('/cms');
    expect(Auth::id())->toBe($this->user->id);

    // The code can't be used twice.
    Auth::logout();
    post('/cms/login/code', ['code' => $code])->assertRedirect(route('sunrice.admin.login'));
    expect(Auth::check())->toBeFalse();
});

it('does not email a code for a wrong password', function () {
    Notification::fake();
    config(['sunrice.auth.two_factor' => true]);

    post('/cms/login', ['email' => 'rina@example.com', 'password' => 'nope'])->assertSessionHasErrors('email');

    Notification::assertNothingSent();
    get('/cms/login/code')->assertRedirect(route('sunrice.admin.login'));
});

it('expires codes', function () {
    Notification::fake();
    config(['sunrice.auth.two_factor' => true]);
    post('/cms/login', ['email' => 'rina@example.com', 'password' => 'secret-pw']);
    $code = lastLoginCode($this->user);

    $this->travel(TwoFactorLogin::LIFETIME_MINUTES + 1)->minutes();

    post('/cms/login/code', ['code' => $code])
        ->assertRedirect(route('sunrice.admin.login'))
        ->assertSessionHas('error');
    expect(Auth::check())->toBeFalse();
});

it('starts over after too many wrong codes', function () {
    Notification::fake();
    config(['sunrice.auth.two_factor' => true]);
    post('/cms/login', ['email' => 'rina@example.com', 'password' => 'secret-pw']);
    $code = lastLoginCode($this->user);
    $wrong = $code === '000000' ? '111111' : '000000';

    for ($i = 1; $i < TwoFactorLogin::MAX_ATTEMPTS; $i++) {
        post('/cms/login/code', ['code' => $wrong])->assertSessionHasErrors('code');
    }
    post('/cms/login/code', ['code' => $wrong])->assertRedirect(route('sunrice.admin.login'))->assertSessionHas('error');

    // The right code no longer works either.
    post('/cms/login/code', ['code' => $code])->assertRedirect(route('sunrice.admin.login'));
    expect(Auth::check())->toBeFalse();
});

it('locks the account out of new codes after many wrong ones', function () {
    Notification::fake();
    config(['sunrice.auth.two_factor' => true]);

    for ($i = 0; $i < TwoFactorLogin::LOCKOUT_ATTEMPTS; $i++) {
        RateLimiter::hit('sunrice-two-factor|'.$this->user->id, TwoFactorLogin::LOCKOUT_SECONDS);
    }

    post('/cms/login', ['email' => 'rina@example.com', 'password' => 'secret-pw'])->assertSessionHasErrors('email');
    Notification::assertNothingSent();
});

it('resends a code, but not more than once a minute', function () {
    Notification::fake();
    config(['sunrice.auth.two_factor' => true]);
    post('/cms/login', ['email' => 'rina@example.com', 'password' => 'secret-pw']);
    $first = lastLoginCode($this->user);

    post('/cms/login/code/resend')->assertSessionHas('error');
    Notification::assertSentToTimes($this->user, LoginCode::class, 1);

    $this->travel(TwoFactorLogin::RESEND_SECONDS + 1)->seconds();
    post('/cms/login/code/resend')->assertSessionHas('success');
    Notification::assertSentToTimes($this->user, LoginCode::class, 2);

    $second = lastLoginCode($this->user);
    if ($first !== $second) {
        // Only the newest code works.
        post('/cms/login/code', ['code' => $first])->assertSessionHasErrors('code');
    }
    post('/cms/login/code', ['code' => $second])->assertRedirect('/cms');
    expect(Auth::id())->toBe($this->user->id);
});

it('explains when the code email cannot be sent', function () {
    config(['sunrice.auth.two_factor' => true]);
    brokenMail();

    post('/cms/login', ['email' => 'rina@example.com', 'password' => 'secret-pw'])
        ->assertSessionHasErrors(['email' => 'We couldn\'t email your login code. Ask an administrator to check the mail (SMTP) settings.']);

    expect(Auth::check())->toBeFalse();
    get('/cms/login/code')->assertRedirect(route('sunrice.admin.login'));
});

it('is switched on in settings, and off from the command line', function () {
    actingAsSuperAdmin();

    get('/cms/settings')->assertInertia(fn (Assert $page) => $page
        ->where('settings.security.two_factor', false)
        ->has('mail.mailer'));

    $settings = SiteSettings::current();
    post('/cms/settings', [...$settings, '_method' => 'PUT', 'security' => ['two_factor' => true]])->assertSessionHasNoErrors();
    expect(TwoFactorLogin::enabled())->toBeTrue();

    artisan('sunrice:two-factor off')->assertSuccessful();
    expect(TwoFactorLogin::enabled())->toBeFalse()
        ->and(SiteSettings::stored()['name'])->toBe($settings['name']);

    artisan('sunrice:two-factor on')->assertSuccessful();
    expect(TwoFactorLogin::enabled())->toBeTrue();
    artisan('sunrice:two-factor maybe')->assertFailed();
});

it('sends a test email from settings', function () {
    actingAsSuperAdmin();
    Notification::fake();

    post('/cms/settings/test-mail', ['test_email' => 'not-an-email'])->assertSessionHasErrors('test_email');

    post('/cms/settings/test-mail', ['test_email' => 'ops@example.com'])->assertSessionHas('success');
    Notification::assertSentOnDemand(TestMail::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'ops@example.com');
});

it('shows the mail server error when the test email fails', function () {
    actingAsSuperAdmin();
    brokenMail();

    post('/cms/settings/test-mail', ['test_email' => 'ops@example.com'])
        ->assertSessionHasErrors('test_email');
    expect(session('errors')->first('test_email'))->toStartWith('Sending failed:');
});

it('only lets settings editors send test emails', function () {
    $this->actingAs($this->user);
    Notification::fake();

    post('/cms/settings/test-mail', ['test_email' => 'ops@example.com'])->assertForbidden();
    Notification::assertNothingSent();
});
