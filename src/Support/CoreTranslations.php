<?php

declare(strict_types=1);

namespace Sunrice\Support;

use Illuminate\Support\Arr;
use Illuminate\Translation\Translator;

/**
 * Laravel ships its own lines (pagination, validation, auth, passwords
 * and the pagination view's words) in English only, and a fresh app has
 * no lang/ folder. Visitors in another language then saw keys such as
 * "pagination.previous". This fills those gaps:
 *
 * - Sunrice's translations of Laravel's lines (resources/lang-core), for
 *   each group the site hasn't published itself (lang/{locale}/{group}.php
 *   always wins);
 * - as a last resort, the English line instead of a raw key.
 */
class CoreTranslations
{
    public static function register(Translator $translator): void
    {
        $root = dirname(__DIR__, 2).'/resources/lang-core';

        // JSON lines ("Showing", "results"…): the site's own lang/{locale}.json overrides these.
        $translator->addJsonPath($root);

        foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $locale = basename($dir);
            foreach (glob($dir.'/*.php') ?: [] as $file) {
                $group = basename($file, '.php');
                if (is_file(lang_path("{$locale}/{$group}.php"))) {
                    continue;
                }
                $lines = [];
                foreach (Arr::dot((array) require $file) as $key => $line) {
                    if (is_string($line)) {
                        $lines["{$group}.{$key}"] = $line;
                    }
                }
                $translator->addLines($lines, $locale);
            }
        }

        $translator->handleMissingKeysUsing(function (string $key, array $replace, ?string $locale, bool $fallback) use ($translator): string {
            if ($locale === 'en') {
                return $key;
            }
            $english = $translator->get($key, $replace, 'en', false);

            return is_string($english) ? $english : $key;
        });
    }
}
