<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Support\Locales;
use Sunrice\Tests\TestCase;
use Workbench\App\Models\User;

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Test helpers
|--------------------------------------------------------------------------
*/

/**
 * Log in as a user carrying the Super Admin role (Gate::before bypass).
 */
function actingAsSuperAdmin(): User
{
    $user = User::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
    $role = Role::findOrCreate(
        config('sunrice.super_admin_role'),
        config('sunrice.auth.guard', 'web'),
    );
    $user->assignRole($role);

    test()->actingAs($user, config('sunrice.auth.guard', 'web'));

    return $user;
}

/**
 * Create a collection with a (possibly empty) blueprint.
 *
 * @param  array<string, mixed>  $settings
 */
function createCollection(string $handle = 'pages', array $settings = [], ?Blueprint $blueprint = null): Collection
{
    $blueprint ??= Blueprint::create([
        'handle' => $handle,
        'title' => ucfirst($handle),
        'fields' => [
            ['handle' => 'body', 'type' => 'textarea', 'label' => 'Body'],
        ],
    ]);

    return Collection::create([
        'handle' => $handle,
        'title' => ucfirst($handle),
        'blueprint_id' => $blueprint->id,
        'settings' => array_merge([
            'route' => '/'.$handle.'/{slug}',
            'has_single' => true,
            'has_archive' => false,
            'translatable' => false,
            'default_sort' => 'manual',
            'per_page' => 12,
        ], $settings),
    ]);
}

/**
 * Create an entry with a main-language translation.
 *
 * @param  array<string, mixed>  $data
 */
function createEntry(Collection $collection, string $title = 'Hello', array $data = [], ?string $status = 'published'): Entry
{
    $locale = Locales::main();
    $entry = Entry::create([
        'collection_id' => $collection->id,
        'status' => $status,
        'published_at' => $status === 'published' ? now() : null,
    ]);

    $entry->translations()->create([
        'collection_id' => $collection->id,
        'locale' => $locale,
        'title' => $title,
        'slug' => Str::slug($title),
        'data' => $data,
        'is_ready' => true,
        'content_published_at' => now(),
    ]);

    return $entry->refresh();
}
