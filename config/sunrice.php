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
        'user_model' => User::class,
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
    | SEO
    |--------------------------------------------------------------------------
    |
    | noindex:      Hide the whole site from search engines (adds
    |               `<meta name="robots" content="noindex, follow">` to every
    |               page). Useful on staging. Single pages can be hidden from
    |               the entry's SEO tab instead.
    | twitter_site: The site's X/Twitter handle for `twitter:site`, e.g. "@acme".
    |
    */
    'seo' => [
        'noindex' => (bool) env('SUNRICE_NOINDEX', false),
        'twitter_site' => env('SUNRICE_TWITTER_SITE'),
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
    |                  backup_disk             null = the asset's own disk
    |                  backup_directory        folder the originals are copied into
    |
    */
    'assets' => [
        'disk' => 'public',
        'directory' => 'sunrice',
        'max_upload_kb' => 20480,
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
            'backup_disk' => null,
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
