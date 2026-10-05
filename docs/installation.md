# Installation

Requirements: PHP 8.3+, Laravel 13, one of SQLite / MySQL / PostgreSQL.

```bash
composer require sunrice/cms
php artisan sunrice:install
```

`sunrice:install` publishes `config/sunrice.php`, publishes the compiled
admin assets to `public/vendor/sunrice`, runs the package migrations
(`sunrice_*` tables plus the spatie/laravel-permission tables), syncs
permissions, creates the `Super Admin` role, and offers to create a
super admin user (`--no-user` skips it).

The install command warns when your user model does not use
`Spatie\Permission\Traits\HasRoles` — add it manually:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Open the admin at `/cms` (configurable via `sunrice.admin.path` /
`SUNRICE_ADMIN_PATH`).

## Scheduler

Add the Laravel scheduler so scheduled entries publish and old
submissions prune:

```
* * * * * php artisan schedule:run
```

## Upgrades

After every package upgrade, republish the compiled admin assets:

```bash
php artisan sunrice:publish-assets
php artisan migrate
php artisan sunrice:sync-permissions
```

No `npm`/`vite` step is required in the host app — the admin ships
prebuilt assets.
