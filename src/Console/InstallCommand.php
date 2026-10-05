<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Sunrice\Permissions\SyncPermissions;

/**
 * sunrice:install — publishes config and assets, runs migrations,
 * syncs permissions and creates the Super Admin role. Optionally
 * creates a super admin user (interactive or --no-user).
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
        $this->call('migrate', ['--force' => true]);

        $result = $sync->handle();
        $this->components->twoColumnDetail('Permissions', "{$result['created']} created");

        $guard = config('sunrice.auth.guard', 'web');
        $role = Role::findOrCreate(config('sunrice.super_admin_role', 'Super Admin'), $guard);
        $this->components->twoColumnDetail('Super admin role', $role->name);

        $this->checkUserModel();

        if (! $this->option('no-user')) {
            $this->createUser($role);
        }

        $this->components->info('Done. Admin panel: /'.config('sunrice.admin.path', 'cms'));
        $this->components->warn('Remember to add a scheduler entry: * * * * * php artisan schedule:run');

        return self::SUCCESS;
    }

    protected function checkUserModel(): void
    {
        $model = config('sunrice.auth.user_model');
        if (! class_exists($model)) {
            return;
        }

        if (! in_array(\Spatie\Permission\Traits\HasRoles::class, class_uses_recursive($model), true)) {
            $this->components->warn("Add `use \\Spatie\\Permission\\Traits\\HasRoles;` to {$model} to use roles and permissions.");
        }
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
            ['name' => $name, 'password' => \Illuminate\Support\Facades\Hash::make($password)],
        );

        if (method_exists($user, 'assignRole')) {
            $user->assignRole($role);
            $this->components->twoColumnDetail('Super admin', $email);
        }
    }
}
