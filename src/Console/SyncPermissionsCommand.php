<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use Sunrice\Permissions\SyncPermissions;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'sunrice:sync-permissions';

    protected $description = 'Sync Sunrice permissions with collections, taxonomies, forms and resources';

    public function handle(SyncPermissions $sync): int
    {
        $result = $sync->handle();

        $this->info("Synced permissions: {$result['created']} created, {$result['deleted']} deleted.");

        return self::SUCCESS;
    }
}
