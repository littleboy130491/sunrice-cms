<?php

declare(strict_types=1);

namespace Sunrice\Support;

class Locales
{
    public static function main(): string
    {
        return (string) config('sunrice.locales.main', 'id');
    }

    /**
     * @return array<int, string>
     */
    public static function available(): array
    {
        return array_values(config('sunrice.locales.available', [static::main()]));
    }

    /**
     * @return array<string, string>
     */
    public static function names(): array
    {
        return config('sunrice.locales.names', []);
    }

    public static function name(string $locale): string
    {
        return static::names()[$locale] ?? $locale;
    }

    public static function isMain(?string $locale): bool
    {
        return $locale === null || $locale === static::main();
    }

    public static function isAvailable(string $locale): bool
    {
        return in_array($locale, static::available(), true);
    }

    /**
     * URL prefix for a locale: '' for the main language, '/{locale}' otherwise.
     */
    public static function prefix(?string $locale): string
    {
        return static::isMain($locale) ? '' : '/'.$locale;
    }

    /**
     * Active locale: the app locale when configured, else the main one.
     */
    public static function current(): string
    {
        $locale = app()->getLocale();

        return static::isAvailable($locale) ? $locale : static::main();
    }
}
