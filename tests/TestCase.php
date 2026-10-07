<?php

declare(strict_types=1);

namespace Sunrice\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\ServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Honeypot\HoneypotServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Sitemap\SitemapServiceProvider;
use Sunrice\SunriceServiceProvider;
use Workbench\App\Models\User;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            SunriceServiceProvider::class,
            ServiceProvider::class,
            PermissionServiceProvider::class,
            HoneypotServiceProvider::class,
            SitemapServiceProvider::class,
            McpServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sunrice.locales', [
            'main' => 'id',
            'available' => ['id', 'en'],
            'names' => ['id' => 'Bahasa Indonesia', 'en' => 'English'],
        ]);
        $app['config']->set('sunrice.auth.user_model', User::class);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fve4Qp/sF3rJf2fDR2lHo4SY9mZhJb+s=');
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../workbench/database/migrations');
    }

    /**
     * Host-application routes are defined while the app boots, before
     * Sunrice's catch-all — mirroring a real host app.
     */
    protected function defineRoutes($router): void
    {
        if (file_exists($file = __DIR__.'/../workbench/routes/web.php')) {
            $router->middleware('web')->group($file);
        }
    }
}
