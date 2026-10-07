<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

/** Every `sunrice::frontend.*` key the package uses, from its views, stubs and code. */
function usedFrontendKeys(): array
{
    $keys = [];
    foreach (['resources', 'stubs', 'src'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/'.$dir));
        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                preg_match_all("/sunrice::frontend\.([a-z0-9_]+)/", (string) file_get_contents($file->getPathname()), $m);
                $keys = [...$keys, ...$m[1]];
            }
        }
    }

    return array_values(array_diff(array_unique($keys), ['key']));
}

it('has every frontend line the package uses in each shipped language', function () {
    foreach (['en', 'id'] as $locale) {
        $lines = Arr::dot(require dirname(__DIR__, 2)."/resources/lang/{$locale}/frontend.php");
        expect(array_diff(usedFrontendKeys(), array_keys($lines)))->toBe([], "Missing in {$locale}");
    }
});

it('gives Laravel\'s own lines in Indonesian when the site has no lang files', function () {
    app()->setLocale('id');

    expect(__('pagination.previous'))->toBe('&laquo; Sebelumnya')
        ->and(__('validation.required', ['attribute' => 'Nama']))->toBe('Nama wajib diisi.')
        ->and(__('auth.failed'))->toBe('Email atau kata sandi salah.')
        ->and(__('Showing'))->toBe('Menampilkan');
});

it('falls back to English instead of showing a key', function () {
    app()->setLocale('fr');
    app('translator')->setFallback('fr');

    expect(__('pagination.next'))->toBe('Next &raquo;')
        ->and(__('sunrice::frontend.submit'))->toBe('Submit')
        ->and(__('validation.required', ['attribute' => 'name']))->toBe('The name field is required.');
});

it('covers every Laravel validation rule in Indonesian', function () {
    $en = Arr::dot(require dirname(__DIR__, 2).'/vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
    $id = Arr::dot(require dirname(__DIR__, 2).'/resources/lang-core/id/validation.php');

    expect(array_values(array_diff(array_keys($en), array_keys($id), ['custom.attribute-name.rule-name'])))->toBe([]);
});
