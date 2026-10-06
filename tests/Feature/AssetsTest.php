<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Sunrice\Actions\Assets\CreateFolder;
use Sunrice\Actions\Assets\DeleteFolder;
use Sunrice\Actions\Assets\ForceDeleteAsset;
use Sunrice\Actions\Assets\MoveAsset;
use Sunrice\Actions\Assets\RenameFolder;
use Sunrice\Actions\Assets\ReplaceAsset;
use Sunrice\Actions\Assets\RestoreAsset;
use Sunrice\Actions\Assets\TrashAsset;
use Sunrice\Actions\Assets\UpdateAssetMeta;
use Sunrice\Actions\Assets\UploadAsset;
use Sunrice\Models\Asset;
use Sunrice\Models\AssetFolder;
use Sunrice\Models\Reference;
use Workbench\App\Models\User;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    actingAsSuperAdmin();
    Storage::fake('public');
});

it('uploads an asset to the configured disk path', function () {
    $file = UploadedFile::fake()->image('Hero Image.jpg', 1200, 600);

    $asset = app(UploadAsset::class)->handle($file);

    expect($asset->disk)->toBe('public')
        ->and($asset->path)->toStartWith('sunrice/'.date('Y/m').'/')
        ->and($asset->filename)->toBe('Hero Image.jpg')
        ->and($asset->width)->toBe(1200)
        ->and($asset->height)->toBe(600)
        ->and($asset->mime_type)->toContain('image/');

    Storage::disk('public')->assertExists($asset->path);
});

it('rejects uploads over the configured size limit', function () {
    config()->set('sunrice.assets.max_upload_kb', 1); // 1 KB

    $file = UploadedFile::fake()->create('big.bin', 64);

    app(UploadAsset::class)->handle($file);
})->throws(ValidationException::class);

it('generates configured image sizes', function () {
    config()->set('sunrice.assets.image_sizes', ['thumb' => [100, 100, 'crop'], 'fit' => [50, null, 'fit']]);

    $file = UploadedFile::fake()->image('photo.png', 400, 200);
    $asset = app(UploadAsset::class)->handle($file);

    expect($asset->sizes)->toHaveKeys(['thumb', 'fit']);
    foreach ($asset->sizes as $path) {
        Storage::disk('public')->assertExists($path);
        expect($path)->toEndWith('-'.array_search($path, $asset->sizes, true).'.png');
    }
});

it('skips size generation for non-images and SVGs', function () {
    $doc = app(UploadAsset::class)->handle(UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'));
    expect($doc->sizes)->toBe([]);

    $svg = UploadedFile::fake()->createWithContent('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $asset = app(UploadAsset::class)->handle($svg);
    expect($asset->sizes)->toBe([]);
});

it('replaces a file in place, bumping the version', function () {
    $asset = app(UploadAsset::class)->handle(UploadedFile::fake()->image('a.png', 100, 100));
    $oldPath = $asset->path;

    $updated = app(ReplaceAsset::class)->handle($asset, UploadedFile::fake()->image('b.png', 200, 100));

    expect($updated->path)->toBe($oldPath)
        ->and($updated->version)->toBe(2)
        ->and($updated->width)->toBe(200);
    Storage::disk('public')->assertExists($oldPath);
    foreach ($updated->sizes as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

it('updates meta and moves assets between folders', function () {
    $asset = app(UploadAsset::class)->handle(UploadedFile::fake()->image('a.png'));
    $folder = app(CreateFolder::class)->handle('Banners');

    $asset = app(UpdateAssetMeta::class)->handle($asset, ['title' => 'Banner', 'alt' => 'Alt']);
    expect($asset->title)->toBe('Banner')->and($asset->alt)->toBe('Alt');

    $asset = app(MoveAsset::class)->handle($asset, $folder->id);
    expect($asset->folder_id)->toBe($folder->id);
});

it('trashes, restores and force deletes assets with their files', function () {
    $asset = app(UploadAsset::class)->handle(UploadedFile::fake()->image('a.png'));
    $path = $asset->path;

    app(TrashAsset::class)->handle($asset);
    expect($asset->fresh()->trashed())->toBeTrue();

    $asset = app(RestoreAsset::class)->handle($asset);
    expect($asset->trashed())->toBeFalse();

    app(ForceDeleteAsset::class)->handle($asset, force: true);
    expect(Asset::withTrashed()->find($asset->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('blocks force delete while the asset has usages unless forced', function () {
    $asset = app(UploadAsset::class)->handle(UploadedFile::fake()->image('a.png'));
    Reference::query()->create([
        'source_type' => 'entry',
        'source_id' => 1,
        'target_type' => 'asset',
        'target_id' => $asset->id,
        'field_path' => 'hero',
    ]);

    app(ForceDeleteAsset::class)->handle($asset);
})->throws(ValidationException::class);

it('creates, renames and deletes folders (empty only)', function () {
    $parent = app(CreateFolder::class)->handle('Parent');
    $child = app(CreateFolder::class)->handle('Child', $parent->id);

    expect($child->parent_id)->toBe($parent->id);

    $renamed = app(RenameFolder::class)->handle($parent, 'Renamed');
    expect($renamed->name)->toBe('Renamed');

    // Deleting a non-empty folder throws; deleting the empty child works.
    try {
        app(DeleteFolder::class)->handle($parent);
        $this->fail('Expected ValidationException');
    } catch (ValidationException) {
    }
    app(DeleteFolder::class)->handle($child);
    expect(AssetFolder::find($child->id))->toBeNull();

    app(DeleteFolder::class)->handle($parent);
    expect(AssetFolder::find($parent->id))->toBeNull();
});

// ---------------- admin endpoints ----------------

it('lists assets in the admin with folder and search filters', function () {
    app(UploadAsset::class)->handle(UploadedFile::fake()->image('find-me.png'));
    app(UploadAsset::class)->handle(UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'));

    get('/cms/assets')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Assets/Index')
            ->has('assets.data', 2));

    get('/cms/assets?search=find-me')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('assets.data', 1));

    get('/cms/assets?type=image')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('assets.data', 1));
});

it('uploads via the admin endpoint', function () {
    post('/cms/assets', [
        'file' => UploadedFile::fake()->image('x.png'),
        'title' => 'X',
    ])->assertRedirect();

    expect(Asset::query()->where('filename', 'x.png')->exists())->toBeTrue();
});

it('exposes usages via the show endpoint', function () {
    $asset = app(UploadAsset::class)->handle(UploadedFile::fake()->image('a.png'));
    Reference::query()->create([
        'source_type' => 'entry', 'source_id' => 7,
        'target_type' => 'asset', 'target_id' => $asset->id, 'field_path' => 'hero',
    ]);

    get("/cms/assets/{$asset->id}", ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('usages.0.source_type', 'entry')
        ->assertJsonPath('usages.0.source_id', 7);
});

it('replaces and trashes via admin endpoints', function () {
    $asset = app(UploadAsset::class)->handle(UploadedFile::fake()->image('a.png'));

    post("/cms/assets/{$asset->id}/replace", [
        'file' => UploadedFile::fake()->image('new.png'),
    ])->assertRedirect();
    expect($asset->fresh()->version)->toBe(2);

    delete("/cms/assets/{$asset->id}")->assertRedirect();
    expect($asset->fresh()->trashed())->toBeTrue();
});

it('enforces asset permissions', function () {
    // Fresh user without any sunrice permissions.
    $user = User::query()->create([
        'name' => 'Nobody',
        'email' => 'nobody@example.com',
        'password' => bcrypt('password'),
    ]);
    $this->actingAs($user, 'web');

    get('/cms/assets')->assertForbidden();
    post('/cms/assets', ['file' => UploadedFile::fake()->image('x.png')])->assertForbidden();
});
