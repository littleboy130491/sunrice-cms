<?php

declare(strict_types=1);

namespace Sunrice\Translation;

use Closure;
use Illuminate\Support\Facades\File;

/**
 * Translates the application's Laravel language files: every PHP file
 * under lang/{from}/ (including subfolders) and lang/{from}.json, written
 * to the same place under the target language. Existing keys are kept
 * unless $force is set; keys only present in the target file are kept.
 */
class LangFileTranslator
{
    /** Separator for flattened nested keys; chosen so it cannot clash with real keys. */
    protected const SEPARATOR = "\x1F";

    protected ?Closure $reporter = null;

    public function __construct(
        protected Translator $translator,
        protected string $from,
        protected string $to,
        protected bool $force = false,
        protected bool $dryRun = false,
        protected TranslationStats $stats = new TranslationStats,
        protected ?string $langPath = null,
    ) {
        $this->langPath ??= lang_path();
    }

    public function onProgress(Closure $reporter): static
    {
        $this->reporter = $reporter;

        return $this;
    }

    public function stats(): TranslationStats
    {
        return $this->stats;
    }

    public function translate(): void
    {
        $sourceDir = $this->langPath.DIRECTORY_SEPARATOR.$this->from;
        if (is_dir($sourceDir)) {
            foreach (File::allFiles($sourceDir) as $file) {
                if ($file->getExtension() === 'php') {
                    $this->phpFile($file->getRelativePathname());
                }
            }
        }

        if (is_file($this->langPath.DIRECTORY_SEPARATOR.$this->from.'.json')) {
            $this->jsonFile();
        }
    }

    protected function phpFile(string $relative): void
    {
        $source = $this->load($this->langPath."/{$this->from}/{$relative}");
        $targetPath = $this->langPath."/{$this->to}/{$relative}";
        $target = is_file($targetPath) ? $this->load($targetPath) : [];

        $result = $this->translateMap($this->flatten($source), $this->flatten($target), "Laravel language file {$relative}");
        if ($result !== null) {
            File::ensureDirectoryExists(dirname($targetPath));
            File::put($targetPath, "<?php\n\nreturn ".$this->export($this->unflatten($result)).";\n");
        }
    }

    protected function jsonFile(): void
    {
        $source = (array) json_decode((string) File::get($this->langPath."/{$this->from}.json"), true);
        $targetPath = $this->langPath."/{$this->to}.json";
        $target = is_file($targetPath) ? (array) json_decode((string) File::get($targetPath), true) : [];

        $result = $this->translateMap(
            array_filter($source, 'is_string'),
            array_filter($target, 'is_string'),
            "Laravel language file {$this->from}.json",
        );
        if ($result !== null) {
            File::put($targetPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        }
    }

    /**
     * Returns the merged target map (source key order, then target-only
     * keys) or null when nothing needs writing.
     *
     * @param  array<string, string>  $source
     * @param  array<string, string>  $target
     * @return array<string, string>|null
     */
    protected function translateMap(array $source, array $target, string $label): ?array
    {
        $pending = [];
        foreach ($source as $key => $value) {
            $existing = $target[$key] ?? null;
            if (! $this->force && is_string($existing) && $existing !== '' && $existing !== $value) {
                $this->stats->skipped++;

                continue;
            }
            if (trim($value) !== '') {
                $pending[$key] = $value;
            }
        }

        if ($pending === []) {
            return null;
        }

        if ($this->dryRun) {
            $this->stats->pending += count($pending);
            $this->report("{$label}: ".count($pending).' string(s) to translate');

            return null;
        }

        try {
            $translated = $this->translator->translate($pending, $this->from, $this->to, 'Interface text of a Laravel web application');
        } catch (\Throwable $e) {
            $this->stats->failed += count($pending);
            $this->stats->errors[] = "{$label}: {$e->getMessage()}";
            $this->report("{$label}: failed ({$e->getMessage()})");

            return null;
        }

        $merged = [];
        $written = 0;
        foreach ($source as $key => $value) {
            if (isset($pending[$key])) {
                if (isset($translated[$key])) {
                    $merged[$key] = $translated[$key];
                    $written++;
                } else {
                    $this->stats->failed++;
                    if (isset($target[$key])) {
                        $merged[$key] = $target[$key];
                    }
                }
            } elseif (isset($target[$key])) {
                $merged[$key] = $target[$key];
            }
        }
        $merged += $target;

        $this->stats->translated += $written;
        $this->report("{$label}: translated {$written} string(s)");

        return $written > 0 ? $merged : null;
    }

    /** @return array<string, mixed> */
    protected function load(string $path): array
    {
        $data = require $path;

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<array-key, mixed>  $array
     * @return array<string, string>
     */
    protected function flatten(array $array, string $prefix = ''): array
    {
        $flat = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $flat += $this->flatten($value, $prefix.$key.self::SEPARATOR);
            } elseif (is_string($value)) {
                $flat[$prefix.$key] = $value;
            }
        }

        return $flat;
    }

    /**
     * @param  array<string, string>  $flat
     * @return array<array-key, mixed>
     */
    protected function unflatten(array $flat): array
    {
        $nested = [];
        foreach ($flat as $path => $value) {
            $nested = $this->setPath($nested, explode(self::SEPARATOR, (string) $path), $value);
        }

        return $nested;
    }

    /**
     * @param  array<array-key, mixed>  $array
     * @param  array<int, string>  $segments
     * @return array<array-key, mixed>
     */
    protected function setPath(array $array, array $segments, string $value): array
    {
        $key = (string) array_shift($segments);
        if ($segments === []) {
            $array[$key] = $value;

            return $array;
        }

        $child = isset($array[$key]) && is_array($array[$key]) ? $array[$key] : [];
        $array[$key] = $this->setPath($child, $segments, $value);

        return $array;
    }

    /**
     * Short-array PHP source for a language array.
     *
     * @param  array<array-key, mixed>  $array
     */
    protected function export(array $array, int $depth = 1): string
    {
        $indent = str_repeat('    ', $depth);
        $lines = [];
        foreach ($array as $key => $value) {
            $exportedKey = is_int($key) ? $key : var_export((string) $key, true);
            $exportedValue = is_array($value) ? $this->export($value, $depth + 1) : var_export($value, true);
            $lines[] = "{$indent}{$exportedKey} => {$exportedValue},";
        }

        return $lines === [] ? '[]' : "[\n".implode("\n", $lines)."\n".str_repeat('    ', $depth - 1).']';
    }

    protected function report(string $line): void
    {
        if ($this->reporter !== null) {
            ($this->reporter)($line);
        }
    }
}
