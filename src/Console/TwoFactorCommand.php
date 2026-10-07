<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use Sunrice\Auth\TwoFactorLogin;
use Sunrice\Support\SiteSettings;

/**
 * sunrice:two-factor — turns two-factor login on or off from the server.
 * The way back in when mail stops working and nobody can get a code.
 */
class TwoFactorCommand extends Command
{
    protected $signature = 'sunrice:two-factor {state? : on or off (omit to show the current state)}';

    protected $description = 'Turn two-factor (emailed code) login on or off';

    public function handle(): int
    {
        $state = $this->argument('state');

        if ($state === null) {
            $this->components->info('Two-factor login is '.(TwoFactorLogin::enabled() ? 'on' : 'off').'.');

            return self::SUCCESS;
        }

        if (! in_array($state, ['on', 'off'], true)) {
            $this->components->error('Use "on" or "off".');

            return self::FAILURE;
        }

        $stored = SiteSettings::stored();
        $stored['security'] = [...(array) ($stored['security'] ?? []), 'two_factor' => $state === 'on'];
        SiteSettings::save($stored);

        $this->components->info("Two-factor login is now {$state}.");

        return self::SUCCESS;
    }
}
