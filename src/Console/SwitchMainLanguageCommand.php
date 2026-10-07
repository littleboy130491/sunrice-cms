<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Sunrice\Actions\Settings\SwitchMainLanguage;
use Sunrice\Support\Locales;

/**
 * sunrice:switch-main-language — makes another language the main one
 * (the unprefixed, original language) after content exists. The
 * Settings page locks the main language; this converts stored content
 * so nothing is lost. See SwitchMainLanguage for what changes.
 */
class SwitchMainLanguageCommand extends Command
{
    protected $signature = 'sunrice:switch-main-language
        {locale : Language code to make the main language, e.g. en}
        {--dry-run : Only report what would block or change the switch}
        {--copy-missing : Give entries and terms without that language a copy of the current main-language content}
        {--force : Skip the confirmation}';

    protected $description = 'Make another language the main language, converting existing content';

    public function handle(SwitchMainLanguage $switch): int
    {
        $to = (string) $this->argument('locale');
        $from = Locales::main();

        try {
            $report = $switch->report($to);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Switch the main language from '.Locales::name($from)." ({$from}) to ".Locales::name($to)." ({$to})");

        $toName = Locales::name($to)." ({$to})";
        $fromName = Locales::name($from)." ({$from})";
        $missing = count($report['missing_entries']) + count($report['missing_terms']);
        $this->listRows("Entries with no {$toName} version", array_map(
            fn ($e) => "#{$e['id']} {$e['title']} ({$e['collection']})",
            $report['missing_entries'],
        ));
        $this->listRows("Terms with no {$toName} version", array_map(
            fn ($t) => "#{$t['id']} {$t['name']} ({$t['taxonomy']})",
            $report['missing_terms'],
        ));
        $this->listRows("Entries whose {$toName} version isn't marked Ready (it becomes the published main version)", array_map(
            fn ($e) => "#{$e['id']} {$e['title']} ({$e['collection']})",
            $report['unready_entries'],
        ));
        $this->listRows("Globals with no {$toName} values (they get a copy of the {$fromName} values)", $report['missing_globals']);

        $this->newLine();
        $this->line('  After the switch:');
        $this->line("  • {$to} pages move to unprefixed URLs; /{$to}/… redirects there (301).");
        $this->line("  • {$from} pages move to /{$from}/…; their old unprefixed URLs redirect there (301).");
        $this->line("  • Fields that aren't translated per language (images, toggles…) take their values from {$to}.");
        $this->newLine();

        if ($missing > 0 && ! $this->option('copy-missing')) {
            $this->components->error("{$missing} item(s) have no {$toName} version. Translate them first, or re-run with --copy-missing to copy the {$fromName} content into them.");
            if ($this->option('dry-run')) {
                $this->components->info('Dry run: nothing changed.');
            }

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->components->info("Dry run: nothing changed. {$report['entries']} entr".($report['entries'] === 1 ? 'y' : 'ies').' would be converted.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Back up your database first. Switch the main language now?')) {
            $this->components->warn('Cancelled. Nothing changed.');

            return self::FAILURE;
        }

        $stats = $switch->handle($to, (bool) $this->option('copy-missing'));

        $this->components->twoColumnDetail('Entries converted', (string) $stats['entries']);
        $this->components->twoColumnDetail('Entries copied from '.$from, (string) $stats['copied_entries']);
        $this->components->twoColumnDetail('Terms copied from '.$from, (string) $stats['copied_terms']);
        $this->components->twoColumnDetail('Globals copied from '.$from, (string) $stats['copied_globals']);
        $this->components->info(Locales::name($to)." ({$to}) is now the main language.");

        return self::SUCCESS;
    }

    /** @param array<int, string> $rows */
    protected function listRows(string $title, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $this->newLine();
        $this->line('  <fg=yellow>'.$title.' ('.count($rows).'):</>');
        foreach (array_slice($rows, 0, 50) as $row) {
            $this->line('    '.$row);
        }
        if (count($rows) > 50) {
            $this->line('    … and '.(count($rows) - 50).' more');
        }
    }
}
