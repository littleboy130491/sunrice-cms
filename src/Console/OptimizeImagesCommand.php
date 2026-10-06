<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Sunrice\Actions\Assets\OptimizeImage;
use Sunrice\Actions\Assets\RestoreOriginalImage;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Asset;

class OptimizeImagesCommand extends Command
{
    protected $signature = 'sunrice:optimize-images
        {--id=* : Only these asset ids (repeat the option for several)}
        {--max-width= : Shrink images wider than this (default: sunrice.assets.optimize.max_width)}
        {--max-height= : Shrink images taller than this (default: sunrice.assets.optimize.max_height)}
        {--quality= : JPEG/WebP/AVIF quality 1-100 (default: sunrice.assets.optimize.quality)}
        {--no-backup : Do not keep a copy of the original files}
        {--dry-run : Show what would change without writing anything}
        {--restore : Put back the backed-up originals instead of optimizing}';

    protected $description = 'Resize and compress image assets in place, keeping a backup of the originals';

    public function handle(OptimizeImage $optimizer, RestoreOriginalImage $restorer): int
    {
        $defaults = (array) config('sunrice.assets.optimize', []);
        $maxWidth = (int) ($this->option('max-width') ?? $defaults['max_width'] ?? 2560);
        $maxHeight = (int) ($this->option('max-height') ?? $defaults['max_height'] ?? 2560);
        $quality = (int) ($this->option('quality') ?? $defaults['quality'] ?? 82);
        $backup = ! $this->option('no-backup') && (bool) ($defaults['backup'] ?? true);
        $dryRun = (bool) $this->option('dry-run');

        if ($maxWidth < 1 || $maxHeight < 1 || $quality < 1 || $quality > 100) {
            $this->error('--max-width and --max-height must be positive and --quality between 1 and 100.');

            return self::FAILURE;
        }

        $query = Asset::query()->where('mime_type', 'like', 'image/%');
        if ($ids = array_filter((array) $this->option('id'))) {
            $query->whereIn('id', array_map('intval', $ids));
        }

        return $this->option('restore')
            ? $this->restore($query, $restorer)
            : $this->optimize($query, $optimizer, $maxWidth, $maxHeight, $quality, $backup, $dryRun);
    }

    /** @param Builder<Asset> $query */
    protected function optimize(Builder $query, OptimizeImage $optimizer, int $maxWidth, int $maxHeight, int $quality, bool $backup, bool $dryRun): int
    {
        $this->line(sprintf(
            '%s images to fit %dx%d at quality %d%s.',
            $dryRun ? 'Checking' : 'Optimizing',
            $maxWidth,
            $maxHeight,
            $quality,
            $dryRun ? ' (dry run, nothing is written)' : ($backup ? ', backing up originals' : ', without backups'),
        ));

        $optimized = 0;
        $skipped = 0;
        $saved = 0;
        $query->chunkById(50, function ($assets) use ($optimizer, $maxWidth, $maxHeight, $quality, $backup, $dryRun, &$optimized, &$skipped, &$saved): void {
            foreach ($assets as $asset) {
                try {
                    $result = $optimizer->handle($asset, $maxWidth, $maxHeight, $quality, $backup, $dryRun);
                } catch (\Throwable $e) {
                    $skipped++;
                    $this->warn("  #{$asset->id} {$asset->filename}: failed ({$e->getMessage()})");

                    continue;
                }

                if ($result['status'] !== 'optimized') {
                    $skipped++;
                    $this->line("  #{$asset->id} {$asset->filename}: skipped ({$result['reason']})", verbosity: 'v');

                    continue;
                }

                $optimized++;
                $saved += $result['before'] - $result['after'];
                $this->line(sprintf(
                    '  #%d %s: %s → %s, %dx%d',
                    $asset->id,
                    $asset->filename,
                    $this->bytes($result['before']),
                    $this->bytes($result['after']),
                    $result['width'],
                    $result['height'],
                ));
            }
        });

        if ($optimized > 0 && ! $dryRun) {
            ContentChanged::dispatch('assets_optimized');
        }

        $this->info(sprintf(
            '%s %d image(s), skipped %d, %s %s.',
            $dryRun ? 'Would optimize' : 'Optimized',
            $optimized,
            $skipped,
            $dryRun ? 'would save' : 'saved',
            $this->bytes(max(0, $saved)),
        ));

        return self::SUCCESS;
    }

    /** @param Builder<Asset> $query */
    protected function restore(Builder $query, RestoreOriginalImage $restorer): int
    {
        $restored = 0;
        $query->chunkById(50, function ($assets) use ($restorer, &$restored): void {
            foreach ($assets as $asset) {
                if ($restorer->handle($asset)) {
                    $restored++;
                    $this->line("  #{$asset->id} {$asset->filename}: restored");
                }
            }
        });

        if ($restored > 0) {
            ContentChanged::dispatch('assets_restored');
        }

        $this->info("Restored {$restored} original image(s).");

        return self::SUCCESS;
    }

    protected function bytes(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024).' KB';
    }
}
