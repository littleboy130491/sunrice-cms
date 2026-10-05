<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use Sunrice\Actions\Structure\RenameFieldHandle;
use Sunrice\Models\Blueprint;

/**
 * sunrice:rename-field blueprint:handle from to — renames a field in
 * the blueprint's tree and in all stored entry data.
 */
class RenameFieldCommand extends Command
{
    protected $signature = 'sunrice:rename-field {blueprint : Blueprint handle} {from : current field handle} {to : new field handle}';

    protected $description = 'Rename a field handle in a blueprint and all stored entry data';

    public function handle(RenameFieldHandle $rename): int
    {
        $blueprint = Blueprint::query()->where('handle', $this->argument('blueprint'))->first();
        if ($blueprint === null) {
            $this->error("Blueprint \"{$this->argument('blueprint')}\" not found.");

            return self::FAILURE;
        }

        try {
            $result = $rename->handle($blueprint, $this->argument('from'), $this->argument('to'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Renamed \"{$this->argument('from')}\" to \"{$this->argument('to')}\" "
            ."(updated {$result['updated_entries']} entr(y/ies)).");

        return self::SUCCESS;
    }
}
