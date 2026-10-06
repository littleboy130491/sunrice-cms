<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;

/**
 * sunrice:publish-assets — republishes the compiled admin assets to
 * public/vendor/sunrice. Run after package upgrades.
 */
class PublishAssetsCommand extends Command
{
    protected $signature = 'sunrice:publish-assets';

    protected $description = 'Republish the compiled Sunrice admin assets';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'sunrice-assets', '--force' => true]);

        return self::SUCCESS;
    }
}
