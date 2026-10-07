<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Sunrice\Actions\Assets\UploadAsset;
use Sunrice\Actions\Forms\SaveForm;
use Sunrice\Fields\HydrationContext;
use Sunrice\Fields\Types\File;
use Sunrice\Fields\Types\Link;
use Sunrice\Models\Entry;
use Sunrice\Models\FormSubmission;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;
use Sunrice\Query\JsonField;
use Sunrice\Support\CsvCell;
use Sunrice\Support\Locales;
use Sunrice\Support\SafeSvg;
use Sunrice\Support\SafeUrl;
use Workbench\App\Models\User;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAsSuperAdmin();
});

it('only downloads real uploads of file fields', function () {
    Storage::fake(config('sunrice.forms.upload_disk'));
    $disk = Storage::disk(config('sunrice.forms.upload_disk'));
    $form = app(SaveForm::class)->handle(null, ['handle' => 'apply', 'title' => 'Apply', 'fields' => [
        ['handle' => 'cv', 'type' => 'file'],
        ['handle' => 'note', 'type' => 'text'],
    ]]);
    $disk->put('form-uploads/apply/cv.pdf', 'CV');
    $disk->put('secret.txt', 'SECRET');
    $disk->put('form-uploads/other/private.pdf', 'OTHER');

    $submission = FormSubmission::query()->create(['form_id' => $form->id, 'data' => [
        'cv' => 'form-uploads/apply/cv.pdf',
        // A visitor typed this into a text field.
        'note' => 'form-uploads/apply/../../secret.txt',
    ]]);
    $traversal = FormSubmission::query()->create(['form_id' => $form->id, 'data' => [
        'cv' => 'form-uploads/apply/../other/private.pdf',
    ]]);

    get("/cms/submissions/{$submission->id}/download/cv")->assertOk();
    get("/cms/submissions/{$submission->id}/download/note")->assertNotFound();
    get("/cms/submissions/{$traversal->id}/download/cv")->assertNotFound();
});

it('refuses SVGs that could run script', function (string $svg) {
    expect(SafeSvg::isSafe($svg))->toBeFalse();
    expect(fn () => app(UploadAsset::class)->handle(UploadedFile::fake()->createWithContent('logo.svg', $svg)))
        ->toThrow(ValidationException::class);
})->with([
    'script' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    'event handler' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>',
    'javascript link' => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href=" java&#x09;script:alert(1)"><text>x</text></a></svg>',
    'animated href' => '<svg xmlns="http://www.w3.org/2000/svg"><a><set attributeName="href" to="javascript:alert(1)"/><text>x</text></a></svg>',
    'foreignObject' => '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><iframe xmlns="http://www.w3.org/1999/xhtml" src="x"/></foreignObject></svg>',
    'entity' => '<!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>',
    'not xml' => '<svg><script>alert(1)',
]);

it('accepts ordinary SVG logos', function () {
    $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">'
        .'<defs><linearGradient id="g"><stop offset="0" stop-color="#fff"/></linearGradient></defs>'
        .'<a href="https://example.com"><rect width="10" height="10" fill="url(#g)" style="opacity:.5"/></a><use xlink:href="#g"/></svg>';

    expect(SafeSvg::isSafe($svg))->toBeTrue();
});

it('hashes passwords set in the admin', function () {
    post('/cms/users', ['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'Secret-password-123', 'password_confirmation' => 'Secret-password-123'])
        ->assertSessionHasNoErrors();
    $user = User::query()->where('email', 'rina@example.com')->firstOrFail();
    expect($user->getAttributes()['password'])->not->toBe('Secret-password-123')
        ->and(Hash::check('Secret-password-123', $user->password))->toBeTrue();

    put("/cms/users/{$user->id}", ['password' => 'Another-password-456', 'password_confirmation' => 'Another-password-456'])
        ->assertSessionHasNoErrors();
    expect(Hash::check('Another-password-456', $user->fresh()->password))->toBeTrue();

    // The admin's own login works with it.
    auth()->logout();
    post('/cms/login', ['email' => 'rina@example.com', 'password' => 'Another-password-456'])->assertSessionHasNoErrors();
});

it('validates slug format, but keeps an existing slug on save', function () {
    $pages = createCollection('pages', ['route' => '/{slug}']);

    post('/cms/collections/pages/entries', ['title' => 'A', 'slug' => 'Bad Slug/../x', 'data' => [], 'seo' => []])->assertSessionHasErrors('slug');
    post('/cms/collections/pages/entries', ['title' => 'A', 'slug' => Locales::main(), 'data' => [], 'seo' => []])->assertSessionHasErrors('slug');
    post('/cms/collections/pages/entries', ['title' => 'A', 'slug' => 'about-us', 'data' => [], 'seo' => []])->assertSessionHasNoErrors();

    // Saved before slugs were checked.
    $legacy = createEntry($pages, 'Legacy');
    $legacy->translations()->update(['slug' => 'Legacy_Page']);
    put("/cms/entries/{$legacy->id}", ['locale' => Locales::main(), 'title' => 'Legacy', 'slug' => 'Legacy_Page', 'data' => [], 'seo' => []])
        ->assertSessionHasNoErrors();
    put("/cms/entries/{$legacy->id}", ['locale' => Locales::main(), 'title' => 'Legacy', 'slug' => 'Other Page', 'data' => [], 'seo' => []])
        ->assertSessionHasErrors('slug');
});

it('keeps field paths and operators out of raw SQL', function () {
    expect(fn () => JsonField::where(Entry::query(), 'data', "x') OR 1=1 --", '=', 1, 'number'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => JsonField::where(Entry::query(), 'data', 'price', '= 1 OR 1=1 --', 1, 'number'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => JsonField::where(Entry::query(), 'data', 'meta.price', '>=', 1, 'number'))->not->toThrow(InvalidArgumentException::class);

    post('/cms/structure/blueprints', ['title' => 'X', 'handle' => 'x', 'fields' => [['handle' => "price'", 'type' => 'text']]])
        ->assertSessionHasErrors('fields.0.handle');
});

it('refuses script URLs in menus and link fields', function () {
    expect(SafeUrl::isSafe('javascript:alert(1)'))->toBeFalse()
        ->and(SafeUrl::isSafe(" JaVa\tScRiPt:alert(1)"))->toBeFalse()
        ->and(SafeUrl::isSafe('data:text/html,x'))->toBeFalse()
        ->and(SafeUrl::isSafe('https://example.com'))->toBeTrue()
        ->and(SafeUrl::isSafe('/about'))->toBeTrue()
        ->and(SafeUrl::isSafe('#top'))->toBeTrue()
        ->and(SafeUrl::isSafe('mailto:hi@example.com'))->toBeTrue();

    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    post("/cms/menus/{$menu->id}/items", ['type' => 'url', 'url' => 'javascript:alert(1)', 'labels' => ['en' => 'x']])
        ->assertSessionHasErrors('url');

    $link = new Link;
    expect(validator(['l' => ['type' => 'url', 'url' => 'javascript:alert(1)']], ['l' => $link->rules([])])->fails())->toBeTrue();
    // Saved before the check: never printed.
    expect($link->hydrate(['type' => 'url', 'url' => 'javascript:alert(1)', 'label' => 'x'], [], new HydrationContext('en'))['url'])->toBeNull();
});

it('neutralizes spreadsheet formulas in CSV exports', function () {
    expect(CsvCell::safe('=HYPERLINK("http://evil")'))->toBe('\'=HYPERLINK("http://evil")')
        ->and(CsvCell::safe('@SUM(A1)'))->toBe("'@SUM(A1)")
        ->and(CsvCell::safe('hello'))->toBe('hello')
        ->and(CsvCell::safe(-5))->toBe(-5);

    $form = app(SaveForm::class)->handle(null, ['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'name', 'type' => 'text']]]);
    FormSubmission::query()->create(['form_id' => $form->id, 'data' => ['name' => '=HYPERLINK("http://evil","x")']]);

    $csv = get("/cms/forms/{$form->id}/submissions/export")->assertOk()->streamedContent();
    expect($csv)->toContain('"\'=HYPERLINK(');
});

it('never makes a menu item its own ancestor', function () {
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    $a = MenuItem::query()->create(['menu_id' => $menu->id, 'type' => 'url', 'url' => '/a', 'labels' => ['en' => 'A'], 'sort_order' => 1]);
    $b = MenuItem::query()->create(['menu_id' => $menu->id, 'parent_id' => $a->id, 'type' => 'url', 'url' => '/b', 'labels' => ['en' => 'B'], 'sort_order' => 2]);

    put("/cms/menu-items/{$a->id}", ['parent_id' => $b->id])->assertSessionHasErrors('parent_id');
    post("/cms/menus/{$menu->id}/items/reorder", ['items' => [
        ['id' => $a->id, 'parent_id' => $b->id],
        ['id' => $b->id, 'parent_id' => $a->id],
    ]])->assertSessionHasErrors('items');
    expect($a->fresh()->parent_id)->toBeNull();

    post("/cms/menus/{$menu->id}/items/reorder", ['items' => [
        ['id' => $b->id, 'parent_id' => null],
        ['id' => $a->id, 'parent_id' => $b->id],
    ]])->assertSessionHasNoErrors();
    expect($a->fresh()->parent_id)->toBe($b->id);
});

it('limits form upload size by default', function () {
    expect((new File)->rules(['handle' => 'cv', 'type' => 'file']))->toContain('max:10240')
        ->and((new File)->rules(['handle' => 'cv', 'type' => 'file', 'config' => ['max_kb' => 500]]))->toContain('max:500');
});

it('answers password reset requests the same for unknown emails', function () {
    auth()->logout();

    post('/cms/forgot-password', ['email' => 'nobody@example.com'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');
});
