<?php

declare(strict_types=1);

namespace Sunrice\Support;

use Sunrice\Models\Asset;
use Throwable;

/**
 * White-labelling of the admin panel (Settings → Branding): its name,
 * tagline, logo, font and one global color. Stored with the site
 * settings (sunrice.branding.*).
 */
class Branding
{
    public const DEFAULT_NAME = 'Sunrice';

    public const DEFAULT_TAGLINE = 'Content workspace';

    /**
     * Fonts offered in Settings: key => [family, Bunny Fonts slug]. A null
     * slug loads nothing (the operating system's own font).
     *
     * @var array<string, array{0: string, 1: string|null}>
     */
    public const FONTS = [
        'instrument-sans' => ['Instrument Sans', 'instrument-sans'],
        'inter' => ['Inter', 'inter'],
        'manrope' => ['Manrope', 'manrope'],
        'dm-sans' => ['DM Sans', 'dm-sans'],
        'plus-jakarta-sans' => ['Plus Jakarta Sans', 'plus-jakarta-sans'],
        'figtree' => ['Figtree', 'figtree'],
        'outfit' => ['Outfit', 'outfit'],
        'ibm-plex-sans' => ['IBM Plex Sans', 'ibm-plex-sans'],
        'source-sans-3' => ['Source Sans 3', 'source-sans-3'],
        'nunito-sans' => ['Nunito Sans', 'nunito-sans'],
        'system' => ['System', null],
    ];

    public static function name(): string
    {
        $name = trim((string) config('sunrice.branding.name'));

        return $name !== '' ? $name : self::DEFAULT_NAME;
    }

    public static function tagline(): string
    {
        $tagline = config('sunrice.branding.tagline');

        return is_string($tagline) ? trim($tagline) : self::DEFAULT_TAGLINE;
    }

    public static function font(): string
    {
        $font = (string) config('sunrice.branding.font');

        return array_key_exists($font, self::FONTS) ? $font : 'instrument-sans';
    }

    /** #rrggbb, or null for the built-in neutral palette. */
    public static function color(): ?string
    {
        $color = strtolower(trim((string) config('sunrice.branding.color')));

        return preg_match('/^#[0-9a-f]{6}$/', $color) === 1 ? $color : null;
    }

    public static function logoUrl(): ?string
    {
        $id = config('sunrice.branding.logo');
        if (! is_numeric($id)) {
            return null;
        }

        try {
            return Asset::query()->find((int) $id)?->url();
        } catch (Throwable) {
            return null; // Before the assets table exists (installing).
        }
    }

    /** Whether the panel still carries the Sunrice name (shows "Powered by"). */
    public static function isDefault(): bool
    {
        return self::name() === self::DEFAULT_NAME;
    }

    /** Bunny Fonts stylesheet for the chosen font, or null. */
    public static function fontHref(): ?string
    {
        $slug = self::FONTS[self::font()][1];

        return $slug === null ? null : "https://fonts.bunny.net/css?family={$slug}:400,500,600,700";
    }

    /**
     * CSS custom properties overriding the admin theme (font and global
     * color), for a <style> block in the admin layout.
     */
    public static function css(): string
    {
        $family = self::FONTS[self::font()][1] === null
            ? 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif'
            : '"'.self::FONTS[self::font()][0].'"';
        // html:root / html.dark outrank the stylesheet's :root / .dark,
        // which loads after this block.
        $css = "html:root{--sunrice-font:{$family};}";

        $color = self::color();
        if ($color !== null) {
            $on = self::readableOn($color);
            $vars = "--primary:{$color};--primary-foreground:{$on};--ring:{$color};--sidebar-primary:{$color};--sidebar-primary-foreground:{$on};--sidebar-ring:{$color};";
            $css .= "html:root{{$vars}}html.dark{{$vars}}";
        }

        return $css;
    }

    /** Black or white, whichever reads better on $hex. */
    public static function readableOn(string $hex): string
    {
        [$r, $g, $b] = array_map(fn (string $c) => hexdec($c) / 255, str_split(ltrim($hex, '#'), 2));
        $linear = fn (float $c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);

        // Contrast against white vs black.
        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.05) ? '#ffffff' : '#111111';
    }

    /**
     * For the admin (shared Inertia prop).
     *
     * @return array{name: string, tagline: string, logo: string|null, font: string, color: string|null, is_default: bool}
     */
    public static function shared(): array
    {
        return [
            'name' => self::name(),
            'tagline' => self::tagline(),
            'logo' => self::logoUrl(),
            'font' => self::font(),
            'color' => self::color(),
            'is_default' => self::isDefault(),
        ];
    }
}
