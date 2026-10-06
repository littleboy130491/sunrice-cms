<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Sunrice\Permissions\SyncPermissions;

/**
 * sunrice:install — publishes config and assets, publishes the
 * spatie/laravel-permission migration when the app doesn't have it yet,
 * runs migrations, syncs permissions and creates the Super Admin role.
 * Optionally creates a super admin user (interactive or --no-user).
 * Safe to run again.
 */
class InstallCommand extends Command
{
    protected $signature = 'sunrice:install {--no-user : Skip creating a super admin user}';

    protected $description = 'Install Sunrice CMS';

    public function handle(SyncPermissions $sync): int
    {
        $this->components->info('Installing Sunrice CMS');

        $this->callSilently('vendor:publish', ['--tag' => 'sunrice-config']);
        $this->callSilently('vendor:publish', ['--tag' => 'sunrice-assets', '--force' => true]);
        $this->publishPermissionMigration();
        $this->call('migrate', ['--force' => true]);

        $result = $sync->handle();
        $this->components->twoColumnDetail('Permissions', "{$result['created']} created");

        $guard = config('sunrice.auth.guard', 'web');
        /** @var Role $role */
        $role = Role::findOrCreate(config('sunrice.super_admin_role', 'Super Admin'), $guard);
        $this->components->twoColumnDetail('Super admin role', $role->name);

        $hasRoles = $this->checkUserModel();

        if (! $this->option('no-user')) {
            if ($hasRoles) {
                $this->createUser($role);
            } else {
                $this->components->warn('Skipped creating the super admin user: it could not be given the Super Admin role. Add the HasRoles trait, then run sunrice:install again.');
            }
        }

        $this->components->info('Done. Admin panel: /'.config('sunrice.admin.path', 'cms'));
        $this->components->warn('Remember to add a scheduler entry: * * * * * php artisan schedule:run');

        return self::SUCCESS;
    }

    /**
     * The spatie/laravel-permission tables aren't part of Sunrice's own
     * migrations (an app may already have them), so publish spatie's
     * migration unless the tables exist or the app already published it.
     */
    protected function publishPermissionMigration(): void
    {
        $table = config('permission.table_names.permissions', 'permissions');
        $published = glob(database_path('migrations/*_create_permission_tables.php')) ?: [];

        if (Schema::hasTable($table) || $published !== []) {
            return;
        }

        $this->callSilently('vendor:publish', ['--tag' => 'permission-migrations']);
        $this->components->twoColumnDetail('Permission tables', 'migration published');
    }

    /**
     * Whether the user model can hold roles (uses HasRoles).
     */
    protected function checkUserModel(): bool
    {
        $model = config('sunrice.auth.user_model');
        if (! is_string($model) || ! class_exists($model)) {
            return true; // createUser() reports the missing model.
        }

        if (! in_array(HasRoles::class, class_uses_recursive($model), true)) {
            $this->components->warn("Add `use \\Spatie\\Permission\\Traits\\HasRoles;` to {$model} to use roles and permissions.");

            return false;
        }

        return true;
    }

    protected function createUser(Role $role): void
    {
        $model = config('sunrice.auth.user_model');
        if (! class_exists($model)) {
            $this->components->warn("User model {$model} not found — create the super admin user yourself.");

            return;
        }

        $name = $this->ask('Super admin name', 'Admin');
        $email = $this->ask('Super admin email');
        $password = $this->secret('Super admin password');
        if ($email === null || $password === null || $password === '') {
            $this->components->warn('Skipped user creation (missing email/password).');

            return;
        }

        $user = $model::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)],
        );

        if (! method_exists($user, 'assignRole')) {
            $this->components->warn("User {$email} was created but could not be given the Super Admin role.");

            return;
        }

        $user->assignRole($role);
        $this->components->twoColumnDetail('Super admin', $email);
    }
}
