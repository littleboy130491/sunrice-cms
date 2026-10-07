<?php

declare(strict_types=1);

use Sunrice\Facades\Sunrice;

beforeEach(function () {
    actingAsSuperAdmin();
});

it('loads site scripts and styles in the admin, after its own bundle', function () {
    config(['sunrice.admin.scripts' => ['js/sunrice-fields.js', 'https://cdn.example.com/map-field.js', ''], 'sunrice.admin.styles' => ['/css/fields.css']]);
    Sunrice::registerAdminScript('vendor/acme/fields.js');
    Sunrice::registerAdminStyle('vendor/acme/fields.css');

    $html = $this->get('/cms')->assertOk()->getContent();

    expect($html)
        ->toContain('<script type="module" src="'.asset('js/sunrice-fields.js').'"></script>')
        ->toContain('<script type="module" src="https://cdn.example.com/map-field.js"></script>')
        ->toContain('<script type="module" src="'.asset('vendor/acme/fields.js').'"></script>')
        ->toContain('<link rel="stylesheet" href="'.asset('css/fields.css').'">')
        ->toContain('<link rel="stylesheet" href="'.asset('vendor/acme/fields.css').'">');

    // Site scripts come after the admin bundle (which sets up window.Sunrice).
    $bundle = strpos($html, 'vendor/sunrice/');
    expect($bundle)->not->toBeFalse()
        ->and(strpos($html, 'js/sunrice-fields.js'))->toBeGreaterThan($bundle);
});

it('loads nothing extra by default', function () {
    expect(app(\Sunrice\Sunrice::class)->adminScripts())->toBe([])
        ->and(app(\Sunrice\Sunrice::class)->adminStyles())->toBe([]);
});
