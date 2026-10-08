<?php

declare(strict_types=1);

use Sunrice\Admin\Navigation;
use Sunrice\Facades\Sunrice;
use Workbench\App\Models\User;

/** @return array<string, array<int, array<string, mixed>>> group label => items */
function sidebarFor(mixed $user): array
{
    return collect(app(Navigation::class)->for($user))->mapWithKeys(fn (array $g) => [$g['label'] => $g['items']])->all();
}

it('adds registered links to the sidebar, by group and permission', function () {
    $admin = actingAsSuperAdmin();
    createCollection('pages');
    Sunrice::addNavigationItem('Reports', 'reports', 'chart-column');
    Sunrice::addNavigationItem('Shop', '/shop/admin', 'shopping-cart', 'Content');
    Sunrice::addNavigationItem('Billing', 'https://billing.example.com', 'credit-card', 'Manage', fn ($user) => $user->email === 'admin@example.com');
    Sunrice::addNavigationItem('Secret', 'secret', 'shield', 'Tools', 'no-such-ability');

    $groups = sidebarFor($admin);
    $labels = array_keys($groups);

    // A new group goes before "Manage".
    expect(array_search('Tools', $labels, true))->toBe(array_search('Manage', $labels, true) - 1);
    // Super admins pass every ability check, so they see "Secret" too.
    expect($groups['Tools'])->toBe([
        ['label' => 'Reports', 'href' => 'reports', 'icon' => 'chart-column', 'external' => false],
        ['label' => 'Secret', 'href' => 'secret', 'icon' => 'shield', 'external' => false],
    ]);
    expect(collect($groups['Content'])->firstWhere('label', 'Shop'))->toMatchArray(['href' => '/shop/admin', 'external' => true]);
    expect(collect($groups['Manage'])->last())->toMatchArray(['label' => 'Billing', 'external' => true]);

    $editor = User::query()->create(['name' => 'Ed', 'email' => 'ed@example.com', 'password' => bcrypt('x')]);
    $editorGroups = sidebarFor($editor);
    expect($editorGroups['Tools'] ?? [])->toHaveCount(1)
        ->and(collect($editorGroups)->flatten(1)->pluck('label'))->not->toContain('Billing', 'Secret');
});

it('lets a hook change the whole sidebar', function () {
    $admin = actingAsSuperAdmin();
    Sunrice::navigationUsing(function (array $groups) {
        $groups = array_values(array_filter($groups, fn ($g) => $g['label'] !== 'Structure'));
        array_unshift($groups, ['label' => 'Shop', 'items' => [['label' => 'Orders', 'href' => 'resources/orders', 'icon' => 'package']]]);

        return $groups;
    });

    $groups = sidebarFor($admin);

    expect(array_key_first($groups))->toBe('Shop')
        ->and($groups)->not->toHaveKey('Structure')
        ->and($groups['Shop'][0])->toBe(['label' => 'Orders', 'href' => 'resources/orders', 'icon' => 'package', 'external' => false]);
});
