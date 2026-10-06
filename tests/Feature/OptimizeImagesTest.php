<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Sunrice\Actions\Assets\OptimizeImage;
use Sunrice\Actions\Assets\ReplaceAsset;
use Sunrice\Actions\Assets\UploadAsset;
use Sunrice\Models\Asset;

beforeEach(function () {
    Storage::fake('public');
    actingAsSuperAdmin();
});

function backupPathFor(Asset $asset): string
{
    return OptimizeImage::backupLocation($asset)[1];
}

it('shrinks large images, backs up the original and can restore it', function () {
    $asset = app(UploadAsset::class)->handle(UploadedFile::fake()->image('big.jpg', 3000, 2000));
    $original = Storage::disk('public')->get($asset->path);
    $version = $asset->version;

    $this->artisan('sunrice:optimize-images', ['--max-width' => 1000, '--max-height' => 1000, '--quality' => 60])
        ->expectsOutputToContain('Optimized 1 image(s)')
        ->assertSuccessful();

    $asset->refresh();
    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($asset->path));
    expect($width)->toBe(1000)
        ->and($height)->toBeGreaterThanOrEqual(666)->toBeLessThanOrEqual(667)
        ->and([$asset->width, $asset->height])->toBe([$width, $height])
        ->and($asset->version)->toBe($version + 1)
        ->and(Storage::disk('public')->get(backupPathFor($asset)))->toBe($original);

    $this->artisan('sunrice:optimize-images', ['--restore' => true])
        ->expectsOutputToContain('Restored 1 original image(s)')
        ->assertSuccessful();

    $asset->refresh();
    expect(Storage::disk('public')->get($asset->path))->toBe($original)
        ->and([$asset->width, $asset->height])->toBe([3000, 2000])
        ->and(Storage::disk('public')->exists(backupPathFor($asset)))->toBeFalse();
});

it('uses the configured defaults and never overwrites an existing backup', function () {
    config(['sunrice.assets.optimize.max_width' => 1200, 'sunrice.assets.optimize.max_height' => 1200]);
    $asset = app(UploadAsset::class)->handle(UploadedFile::fake()->image('big.jpg', 2400, 1200));
    $original = Storage::disk('public')->get($asset->path);

    $this->artisan('sunrice:optimize-images')->assertSuccessful();
    expect($asset->refresh()->width)->toBe(1200);

    // A second, stricter run keeps the very first original as the backup.
    $this->artisan('sunrice:optimize-images', ['--max-width' => 600])->assertSuccessful();
    expect($asset->refresh()->width)->toBe(600)
        ->and(Storage::disk('public')->get(backupPathFor($asset)))->toBe($original);
});

it('skips the backup with --no-backup and writes nothing on --dry-run', function () {
    $first = app(UploadAsset::class)->handle(UploadedFile::fake()->image('a.jpg', 3000, 2000));
    $second = app(UploadAsset::class)->handle(UploadedFile::fake()->image('b.jpg', 3000, 2000));
    $secondBytes = Storage::disk('public')->get($second->path);

    $this->artisan('sunrice:optimize-images', ['--id' => [$first->id], '--max-width' => 1000, '--no-backup' => true])
        ->assertSuccessful();
    expect($first->refresh()->width)->toBe(1000)
        ->and(Storage::disk('public')->exists(backupPathFor($first)))->toBeFalse();

    $this->artisan('sunrice:optimize-images', ['--id' => [$second->id], '--max-width' => 1000, '--dry-run' => true])
        ->expectsOutputToContain('Would optimize 1 image(s)')
        ->assertSuccessful();
    expect(Storage::disk('public')->get($second->path))->toBe($secondBytes)
        ->and($second->refresh()->width)->toBe(3000)
        ->and(Storage::disk('public')->exists(backupPathFor($second)))->toBeFalse();
});

it('leaves GIFs untouched and rejects invalid options', function () {
    $gif = app(UploadAsset::class)->handle(UploadedFile::fake()->image('anim.gif', 3000, 2000));
    $bytes = Storage::disk('public')->get($gif->path);

    $this->artisan('sunrice:optimize-images', ['--max-width' => 1000])->assertSuccessful();
    expect(Storage::disk('public')->get($gif->path))->toBe($bytes);

    $this->artisan('sunrice:optimize-images', ['--quality' => 150])->assertFailed();
});

it('drops the backup when the asset file is replaced', function () {
    $asset = app(UploadAsset::class)->handle(UploadedFile::fake()->image('big.jpg', 3000, 2000));
    $this->artisan('sunrice:optimize-images', ['--max-width' => 1000])->assertSuccessful();
    expect(Storage::disk('public')->exists(backupPathFor($asset)))->toBeTrue();

    app(ReplaceAsset::class)->handle($asset->refresh(), UploadedFile::fake()->image('new.jpg', 800, 600));

    expect(Storage::disk('public')->exists(backupPathFor($asset)))->toBeFalse();
});
