<?php

declare(strict_types=1);

it('loads default configuration', function () {
    expect(config('sunrice.admin.path'))->toBe('cms')
        ->and(config('sunrice.locales.main'))->toBe('id')
        ->and(config('sunrice.locales.available'))->toContain('en')
        ->and(config('sunrice.cache.enabled'))->toBeTrue()
        ->and(config('sunrice.revisions.keep'))->toBe(50)
        ->and(config('sunrice.super_admin_role'))->toBe('Super Admin');
});
