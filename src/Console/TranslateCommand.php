<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use Sunrice\Events\ContentChanged;
use Sunrice\Support\Locales;
use Sunrice\Translation\ContentTranslator;
use Sunrice\Translation\LangFileTranslator;
use Sunrice\Translation\TranslationStats;
use Sunrice\Translation\Translator;
use Sunrice\Translation\TranslatorFactory;

class TranslateCommand extends Command
{
    protected const PARTS = ['entries', 'terms', 'globals', 'menus', 'lang'];

    protected $signature = 'sunrice:translate
        {--to=* : Target language codes (default: every configured language except the source)}
        {--from= : Source language (default: sunrice.locales.main)}
        {--only=* : What to translate: entries, terms, globals, menus, lang (default: all)}
        {--collection=* : Only entries of these collection handles}
        {--force : Also re-translate fields that already have a translation}
        {--dry-run : Count what would be translated without calling the API}
        {--driver= : gemini or openrouter (default: SUNRICE_TRANSLATE_DRIVER)}
        {--model= : Model name (default: SUNRICE_TRANSLATE_MODEL or the driver default)}';

    protected $description = 'Machine-translate CMS content and Laravel language files with an LLM (Gemini or OpenRouter)';

    public function handle(TranslatorFactory $factory): int
    {
        $from = (string) ($this->option('from') ?: Locales::main());
        $targets = array_values(array_filter((array) $this->option('to'))) ?: array_values(array_diff(Locales::available(), [$from]));
        $parts = array_values(array_filter((array) $this->option('only'))) ?: self::PARTS;
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        if ($invalid = array_diff($parts, self::PARTS)) {
            $this->error('Unknown --only value(s): '.implode(', ', $invalid).'. Use: '.implode(', ', self::PARTS).'.');

            return self::FAILURE;
        }
        if ($invalid = array_diff([$from, ...$targets], Locales::available())) {
            $this->error('Unknown language(s): '.implode(', ', $invalid).'. Configured: '.implode(', ', Locales::available()).'.');

            return self::FAILURE;
        }
        $targets = array_values(array_diff($targets, [$from]));
        if ($targets === []) {
            $this->warn('No target languages. Add languages to sunrice.locales.available or pass --to.');

            return self::SUCCESS;
        }

        if ($force && ! $dryRun && ! $this->option('no-interaction')
            && ! $this->confirm('--force re-translates and overwrites existing translations. Continue?')) {
            return self::FAILURE;
        }

        try {
            $translator = $dryRun ? $this->nullTranslator() : $factory->make($this->option('driver') ?: null, $this->option('model') ?: null);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $stats = new TranslationStats;
        $report = fn (string $line) => $this->line("  {$line}");

        foreach ($targets as $to) {
            $this->info(($dryRun ? 'Checking' : 'Translating').' '.Locales::name($from).' → '.Locales::name($to).($force ? ' (overwriting existing)' : ''));

            $content = (new ContentTranslator($translator, $from, $to, $force, $dryRun, $stats))->onProgress($report);
            if (in_array('entries', $parts, true)) {
                $content->entries(array_values(array_filter((array) $this->option('collection'))));
            }
            if (in_array('terms', $parts, true)) {
                $content->terms();
            }
            if (in_array('globals', $parts, true)) {
                $content->globals();
            }
            if (in_array('menus', $parts, true)) {
                $content->menus();
            }
            if (in_array('lang', $parts, true)) {
                (new LangFileTranslator($translator, $from, $to, $force, $dryRun, $stats))->onProgress($report)->translate();
            }
        }

        if ($stats->translated > 0) {
            ContentChanged::dispatch('content_translated');
        }

        $this->newLine();
        $this->info($dryRun
            ? "Dry run: {$stats->pending} string(s) would be translated, {$stats->skipped} already translated."
            : "Translated {$stats->translated} string(s), skipped {$stats->skipped} already translated, {$stats->failed} failed.");
        if (! $dryRun && in_array('entries', $parts, true) && $stats->translated > 0) {
            $this->line('Entry translations were saved as drafts: review them in the admin, then mark them Ready.');
        }

        return $stats->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function nullTranslator(): Translator
    {
        return new class implements Translator
        {
            public function translate(array $strings, string $from, string $to, ?string $context = null): array
            {
                return [];
            }
        };
    }
}
