<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Workbench\App\Models\User;

use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

beforeEach(function () {
    User::query()->create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => bcrypt('Right-password-123')]);
});

function loginFrom(string $ip, string $email, string $password = 'wrong'): TestResponse
{
    return withServerVariables(['REMOTE_ADDR' => $ip])->post('/cms/login', ['email' => $email, 'password' => $password]);
}

it('blocks one email from one IP after a few wrong passwords', function () {
    foreach (range(1, 5) as $i) {
        loginFrom('10.0.0.1', 'rina@example.com')->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    // Even the right password waits now; another IP doesn't.
    loginFrom('10.0.0.1', 'rina@example.com', 'Right-password-123')->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Too many login attempts');
    loginFrom('10.0.0.2', 'rina@example.com', 'Right-password-123')->assertSessionHasNoErrors();
});

it('blocks an IP trying many different emails', function () {
    config(['sunrice.auth.throttle.per_ip' => 6]);
    foreach (range(1, 6) as $i) {
        loginFrom('10.0.0.9', "user{$i}@example.com");
    }

    loginFrom('10.0.0.9', 'rina@example.com', 'Right-password-123')->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Too many login attempts');
    expect(auth()->check())->toBeFalse();
});

it('locks an account attacked from many IPs, in minutes', function () {
    config(['sunrice.auth.throttle.per_account' => 4, 'sunrice.auth.throttle.account_lock_seconds' => 900]);
    foreach (range(1, 4) as $i) {
        loginFrom("10.1.0.{$i}", 'Rina@example.com');
    }

    loginFrom('10.1.0.99', 'rina@example.com', 'Right-password-123')->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('15 minutes');
});

it('forgives an email after a successful login', function () {
    foreach (range(1, 4) as $i) {
        loginFrom('10.0.0.1', 'rina@example.com');
    }
    loginFrom('10.0.0.1', 'rina@example.com', 'Right-password-123')->assertSessionHasNoErrors();
    post('/cms/logout');

    foreach (range(1, 4) as $i) {
        loginFrom('10.0.0.1', 'rina@example.com')->assertSessionHasErrors(['email' => __('auth.failed')]);
    }
});

it('logs failed logins and lockouts', function () {
    Log::spy();
    foreach (range(1, 5) as $i) {
        loginFrom('10.0.0.1', 'rina@example.com');
    }

    Log::shouldHaveReceived('notice')->with('Sunrice: failed admin login', ['email' => 'rina@example.com', 'ip' => '10.0.0.1'])->times(5);
    Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => str_contains($message, 'locked') && $context['scope'] === 'pair')->once();
});

it('limits the forgot and reset password forms per IP', function () {
    config(['sunrice.auth.throttle.password_resets_per_minute' => 2]);
    foreach (range(1, 2) as $i) {
        withServerVariables(['REMOTE_ADDR' => '10.2.0.1'])->post('/cms/forgot-password', ['email' => "a{$i}@example.com"])->assertRedirect();
    }

    withServerVariables(['REMOTE_ADDR' => '10.2.0.1'])->post('/cms/forgot-password', ['email' => 'a3@example.com'])->assertStatus(429);
    withServerVariables(['REMOTE_ADDR' => '10.2.0.1'])->post('/cms/reset-password', ['token' => 'x', 'email' => 'a@example.com', 'password' => 'x', 'password_confirmation' => 'x'])->assertStatus(429);
    withServerVariables(['REMOTE_ADDR' => '10.2.0.2'])->post('/cms/forgot-password', ['email' => 'a@example.com'])->assertRedirect();
});
