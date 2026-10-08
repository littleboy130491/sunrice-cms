<?php

declare(strict_types=1);

namespace Sunrice\Actions\Settings;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\Setting;
use Sunrice\Support\Branding;
use Sunrice\Support\Locales;
use Sunrice\Support\SiteSettings;

/**
 * Validates and stores the site settings (see SiteSettings) and the
 * homepage entry.
 */
class SaveSiteSettings
{
    public const LOCALE_PATTERN = '/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})?$/';

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input): void
    {
        $validated = validator($input, [
            ...static::seoRules(),
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'timezone' => ['required', 'timezone:all'],
            'homepage_entry_id' => ['nullable', 'integer', Rule::exists('sunrice_entries', 'id')],
            'locales' => ['required', 'array'],
            'locales.main' => ['required', 'string', 'regex:'.self::LOCALE_PATTERN],
            'locales.available' => ['required', 'array', 'min:1'],
            'locales.available.*' => ['required', 'string', 'distinct', 'regex:'.self::LOCALE_PATTERN],
            'locales.names' => ['array'],
            'locales.names.*' => ['nullable', 'string', 'max:100'],
            'branding' => ['array'],
            'branding.name' => ['nullable', 'string', 'max:60'],
            'branding.tagline' => ['nullable', 'string', 'max:80'],
            'branding.logo' => ['nullable', 'integer', Rule::exists('sunrice_assets', 'id')],
            'branding.font' => ['nullable', Rule::in(array_keys(Branding::FONTS))],
            'branding.color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'code' => ['array'],
            'code.head' => ['nullable', 'string', 'max:20000'],
            'code.body_start' => ['nullable', 'string', 'max:20000'],
            'code.body_end' => ['nullable', 'string', 'max:20000'],
            'security' => ['array'],
            'security.two_factor' => ['boolean'],
        ], [
            'locales.*.regex' => 'Use a language code such as "en", "id" or "pt-BR".',
            'locales.available.*.regex' => 'Use a language code such as "en", "id" or "pt-BR".',
        ])->validate();

        $locales = $validated['locales'];
        $available = array_values(array_unique([$locales['main'], ...$locales['available']]));
        $names = array_filter(
            array_intersect_key((array) ($locales['names'] ?? []), array_flip($available)),
            fn ($name) => is_string($name) && trim($name) !== '',
        );

        // Content is stored per language code: switching the main language
        // after content exists would orphan it.
        if ($locales['main'] !== Locales::main() && EntryTranslation::query()->exists()) {
            throw ValidationException::withMessages([
                'locales.main' => 'The main language can\'t be changed once content exists.',
            ]);
        }

        SiteSettings::save([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'timezone' => $validated['timezone'],
            'locales' => ['main' => $locales['main'], 'available' => $available, 'names' => $names],
            // Edited on Settings → SEO: kept as they are when not sent.
            'seo' => array_key_exists('seo', $input) ? static::seo((array) ($validated['seo'] ?? [])) : (array) (SiteSettings::current()['seo'] ?? []),
            'branding' => [
                'name' => trim((string) ($validated['branding']['name'] ?? '')) ?: Branding::DEFAULT_NAME,
                'tagline' => trim((string) ($validated['branding']['tagline'] ?? '')),
                'logo' => $validated['branding']['logo'] ?? null,
                'font' => $validated['branding']['font'] ?? 'instrument-sans',
                'color' => isset($validated['branding']['color']) ? strtolower($validated['branding']['color']) : null,
            ],
            'code' => [
                'head' => $validated['code']['head'] ?? null,
                'body_start' => $validated['code']['body_start'] ?? null,
                'body_end' => $validated['code']['body_end'] ?? null,
            ],
            'security' => [
                'two_factor' => (bool) ($validated['security']['two_factor'] ?? false),
            ],
        ]);

        Setting::set('homepage_entry_id', $validated['homepage_entry_id'] ?? null);
    }

    /** @return array<string, mixed> */
    public static function seoRules(): array
    {
        return [
            'seo' => ['array'],
            'seo.noindex' => ['boolean'],
            'seo.twitter_site' => ['nullable', 'string', 'max:50', 'regex:/^@?\w+$/'],
            'seo.image' => ['nullable', 'integer', Rule::exists('sunrice_assets', 'id')],
            'seo.title_suffix' => ['boolean'],
            'seo.title_separator' => ['nullable', 'string', 'max:5'],
        ];
    }

    /**
     * The site-wide search engine and sharing settings, from validated input.
     *
     * @param  array<string, mixed>  $seo
     * @return array<string, mixed>
     */
    public static function seo(array $seo): array
    {
        return [
            'noindex' => (bool) ($seo['noindex'] ?? false),
            'twitter_site' => $seo['twitter_site'] ?? null,
            'image' => $seo['image'] ?? null,
            'title_suffix' => (bool) ($seo['title_suffix'] ?? false),
            'title_separator' => trim((string) ($seo['title_separator'] ?? '')) ?: '|',
        ];
    }
}
