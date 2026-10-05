<?php

declare(strict_types=1);

namespace Sunrice\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Sunrice\SunriceServiceProvider;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            SunriceServiceProvider::class,
            \Inertia\ServiceProvider::class,
            \Spatie\Permission\PermissionServiceProvider::class,
            \Spatie\Honeypot\HoneypotServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sunrice.locales', [
            'main' => 'id',
            'available' => ['id', 'en'],
            'names' => ['id' => 'Bahasa Indonesia', 'en' => 'English'],
        ]);
        $app['config']->set('sunrice.auth.user_model', \Workbench\App\Models\User::class);
        $app['config']->set('auth.providers.users.model', \Workbench\App\Models\User::class);
        $app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fve4Qp/sF3rJf2fDR2lHo4SY9mZhJb+s=');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../workbench/database/migrations');
    }
}
