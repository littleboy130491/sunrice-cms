<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Sunrice\Database\Seeders\RolesSeeder;

/**
 * sunrice:seed-roles — creates the default roles (Administrator, Editor,
 * Author, Translator) or tops up their permissions. Safe to run again.
 */
class SeedRolesCommand extends Command
{
    protected $signature = 'sunrice:seed-roles';

    protected $description = 'Create the default Sunrice roles, or add new permissions to them';

    public function handle(RolesSeeder $seeder): int
    {
        $seeder->run();

        foreach (array_keys($seeder->roles()) as $name) {
            $role = Role::findByName($name, config('sunrice.auth.guard', 'web'));
            $this->components->twoColumnDetail($name, $role->permissions()->count().' permissions');
        }

        return self::SUCCESS;
    }
}
