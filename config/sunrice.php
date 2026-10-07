<?php

declare(strict_types=1);
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Admin panel
    |--------------------------------------------------------------------------
    |
    | path:    URL prefix for every admin route (default /cms).
    | domain:  Optional domain constraint for the admin routes.
    | middleware: Middleware applied to all admin routes.
    |
    */
    'admin' => [
        'path' => env('SUNRICE_ADMIN_PATH', 'cms'),
        'domain' => null,
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | guard:      Laravel authentication guard used for the admin.
    | user_model: Eloquent model used for admin users. It must use
    |             Spatie\Permission\Traits\HasRoles for permissions.
    |
    */
    'auth' => [
        'guard' => 'web',
        'user_model' => env('SUNRICE_USER_MODEL', User::class),
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | main:      The site's main language. Its URLs carry no prefix.
    | available: All locales the site serves (includes main).
    | names:     Human-readable names per locale for the admin.
    |
    | Switching between a single-language and a multilingual site only
    | requires editing this section — no migrations are needed.
    |
    */
    'locales' => [
        'main' => 'id',
        'available' => ['id'],
        'names' => ['id' => 'Bahasa Indonesia'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend
    |--------------------------------------------------------------------------
    |
    | enabled:    Set false to disable Sunrice's catch-all frontend route.
    | middleware: Middleware applied to public content routes.
    |
    */
    'frontend' => [
        'enabled' => true,
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | enabled:   Content/query caching (per locale, filters, pagination).
    | store:     Cache store name; null uses the application's default.
    | ttl:       Seconds cached content lives (bounds staleness when the
    |            scheduler is not running).
    | full_page: Optional full-page cache via spatie/laravel-responsecache
    |            (requires PHP 8.4 and the package installed).
    |
    */
    'cache' => [
        'enabled' => true,
        'store' => null,
        'ttl' => 3600,
        'full_page' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Machine translation
    |--------------------------------------------------------------------------
    |
    | Used by `php artisan sunrice:translate` to translate content and the
    | app's language files with an LLM.
    |
    | driver:     gemini (Google AI Studio key) or openrouter (any model).
    | model:      overrides the driver's default model below.
    | batch_size: strings sent per request.
    |
    */
    'translation' => [
        'driver' => env('SUNRICE_TRANSLATE_DRIVER', 'gemini'),
        'model' => env('SUNRICE_TRANSLATE_MODEL'),
        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            'model' => 'gemini-3.5-flash-lite',
        ],
        'openrouter' => [
            'key' => env('OPENROUTER_API_KEY'),
            'model' => 'google/gemini-3.5-flash-lite',
        ],
        'batch_size' => 40,
        'timeout' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    |
    | noindex:      Hide the whole site from search engines (adds
    |               `<meta name="robots" content="noindex, follow">` to every
    |               page). Useful on staging. Single pages can be hidden from
    |               the entry's SEO tab instead.
    | twitter_site: The site's X/Twitter handle for `twitter:site`, e.g. "@acme".
    | description:  Default meta description for pages without their own.
    | image:        Default share image (asset id) for pages without their own.
    |
    | All of these (and the site name, timezone, languages and code snippets
    | below) can be changed in the admin under Settings, which overrides
    | the values here.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Branding (white label)
    |--------------------------------------------------------------------------
    |
    | The admin panel's name, tagline, logo (asset id), font (a key of
    | Sunrice\Support\Branding::FONTS) and global color (#rrggbb, null
    | for the neutral default). Editable under Settings → Branding.
    |
    */
    'branding' => [
        'name' => env('SUNRICE_BRAND_NAME', 'Sunrice'),
        'tagline' => 'Content workspace',
        'logo' => null,
        'font' => 'instrument-sans',
        'color' => env('SUNRICE_BRAND_COLOR'),
    ],

    'seo' => [
        'noindex' => (bool) env('SUNRICE_NOINDEX', false),
        'twitter_site' => env('SUNRICE_TWITTER_SITE'),
        'description' => null,
        'image' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Code snippets
    |--------------------------------------------------------------------------
    |
    | HTML added to every public page by <x-sunrice::code position="…" />:
    | head (end of <head>), body_start (after <body>), body_end (before
    | </body>) — e.g. analytics or tag-manager snippets. Edited under
    | Settings in the admin.
    |
    */
    'code' => [
        'head' => null,
        'body_start' => null,
        'body_end' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Assets
    |--------------------------------------------------------------------------
    |
    | disk:          Laravel storage disk for uploaded files.
    | directory:     Root directory inside the disk.
    | max_upload_kb: Maximum upload size in kilobytes.
    | image_sizes:   Generated sizes: name => [width, height, mode].
    |                mode 'crop' = center cover crop, 'fit' = scale down.
    | optimize:      Defaults for `php artisan sunrice:optimize-images`
    |                (each can be overridden with a command option):
    |                  max_width / max_height  shrink larger images to fit
    |                  quality                 JPEG/WebP/AVIF quality, 1-100
    |                  backup                  keep the original before overwriting
    |                  backup_disk             private disk for the originals (null = the asset's own disk)
    |                  backup_directory        folder the originals are copied into
    |
    */
    'assets' => [
        'disk' => 'public',
        'directory' => 'sunrice',
        'max_upload_kb' => 20480,
        // File types editors may upload. Scripts and HTML are always refused.
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico',
            'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf',
            'zip', 'mp4', 'webm', 'mov', 'm4v', 'mp3', 'wav', 'ogg', 'm4a', 'woff', 'woff2', 'json',
        ],
        'image_sizes' => [
            'thumbnail' => [300, 300, 'crop'],
            'medium' => [800, null, 'fit'],
            'large' => [1600, null, 'fit'],
        ],
        'optimize' => [
            'max_width' => 2560,
            'max_height' => 2560,
            'quality' => 82,
            'backup' => true,
            'backup_disk' => 'local',
            'backup_directory' => 'sunrice-originals',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Forms
    |--------------------------------------------------------------------------
    |
    | upload_disk:      Private disk for form file uploads.
    | prune_after_days: Days to keep submissions (null = never prune).
    | rate_limit:       Submission rate limit per IP + form.
    |
    */
    'forms' => [
        'upload_disk' => 'local',
        'prune_after_days' => null,
        'rate_limit' => ['attempts' => 5, 'per_minutes' => 1],
    ],

    /*
    |--------------------------------------------------------------------------
    | Revisions
    |--------------------------------------------------------------------------
    |
    | keep: Maximum number of revisions kept per translation.
    |
    */
    'revisions' => ['keep' => 50],

    /*
    |--------------------------------------------------------------------------
    | Super admin role
    |--------------------------------------------------------------------------
    |
    | Users with this role bypass every permission check (Gate::before).
    |
    */
    'super_admin_role' => 'Super Admin',
];
