<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use Sunrice\Jobs\GenerateImageSizes;
use Sunrice\Models\Asset;

class RegenerateImageSizesCommand extends Command
{
    protected $signature = 'sunrice:regenerate-images {--id= : Regenerate a single asset id}';

    protected $description = 'Regenerate configured image sizes for all image assets';

    public function handle(GenerateImageSizes $generator): int
    {
        $query = Asset::query()
            ->where('mime_type', 'like', 'image/%')
            ->where('mime_type', 'not like', '%svg%');

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        }

        $count = 0;
        $query->chunkById(100, function ($assets) use ($generator, &$count): void {
            foreach ($assets as $asset) {
                $generator->handle($asset);
                $count++;
            }
        });

        $this->info("Regenerated sizes for {$count} asset(s).");

        return self::SUCCESS;
    }
}
