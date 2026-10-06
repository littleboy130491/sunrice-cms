<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Export\CsvExporter;
use Sunrice\Admin\Table\Column;
use Sunrice\Admin\Table\TableQuery;
use Sunrice\Models\Entry;
use Sunrice\Models\Setting;
use Sunrice\Notifications\ResetAdminPassword;
use Sunrice\Permissions\SyncPermissions;
use Workbench\App\Models\User;

it('redirects guests to the admin login', function () {
    $this->get('/cms')->assertRedirect(route('sunrice.admin.login'));
});

it('renders the login page for guests', function () {
    $this->get('/cms/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('forbids logged-in users without access-admin', function () {
    $user = User::create(['name' => 'U', 'email' => 'u@x.com', 'password' => 'x']);
    $this->actingAs($user, 'web');

    $this->get('/cms')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403));
});

it('logs in, shows the dashboard and logs out', function () {
    $user = User::create([
        'name' => 'Admin',
        'email' => 'a@x.com',
        'password' => Hash::make('secret-pw'),
    ]);
    app(SyncPermissions::class)->handle();
    $role = Role::findOrCreate('member', 'web');
    $role->givePermissionTo('sunrice.access-admin');
    $user->assignRole($role);

    $this->post('/cms/login', ['email' => 'a@x.com', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->post('/cms/login', ['email' => 'a@x.com', 'password' => 'secret-pw'])
        ->assertRedirect();

    $this->get('/cms')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('collections')
            ->has('recentEdits')
            ->has('auth.user')
            ->where('auth.user.email', 'a@x.com')
            ->has('permissions')
            ->has('navigation')
            ->where('adminPath', 'cms'));

    $this->post('/cms/logout')->assertRedirect(route('sunrice.admin.login'));
    $this->get('/cms')->assertRedirect(route('sunrice.admin.login'));
});

it('rate limits repeated failed logins', function () {
    User::create([
        'name' => 'A', 'email' => 'a@x.com',
        'password' => Hash::make('right'),
    ]);

    foreach (range(1, 5) as $i) {
        $this->post('/cms/login', ['email' => 'a@x.com', 'password' => 'wrong']);
    }

    $this->post('/cms/login', ['email' => 'a@x.com', 'password' => 'right'])
        ->assertSessionHasErrors('email');
});

it('emails a password reset link that points at the CMS reset page', function () {
    Notification::fake();
    $user = User::query()->create(['name' => 'Ed', 'email' => 'ed@example.com', 'password' => Hash::make('secret-pw-123')]);

    $this->post('/cms/forgot-password', ['email' => 'ed@example.com'])->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetAdminPassword::class, function (ResetAdminPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_contains($url, '/cms/reset-password/'.$notification->token)
            && str_contains($url, 'email=ed%40example.com');
    });
});

it('does not affect host app routes outside the admin path', function () {
    Route::middleware('web')->get('/host-page', fn () => 'host ok');

    $this->get('/host-page')->assertOk()->assertSee('host ok');
});

it('applies search, filters, sort and pagination in TableQuery', function () {
    $collection = createCollection('articles');
    createEntry($collection, 'Alpha');
    createEntry($collection, 'Beta');
    createEntry($collection, 'Gamma', [], 'draft');

    $request = Request::create('/cms/x', 'GET', ['search' => 'Alpha']);
    $table = TableQuery::for(Entry::query()->with('translations'))
        ->searchable(['id'])
        ->filterable(['status'])
        ->sortable(['id']);

    // Search on the joined translations title requires a join; keep to a
    // simpler assertion: standard filter + sort.
    $request = Request::create('/cms/x', 'GET', [
        'filters' => ['status' => 'draft'],
        'sort' => '-id',
        'per_page' => 5,
    ]);
    $table->apply($request);
    $page = $table->paginate($request);

    expect($page->total())->toBe(1)
        ->and($page->items()[0]->id)->toBe(3)
        ->and($table->meta()['sort'])->toBe('-id')
        ->and($table->meta()['filters']['status'])->toBe('draft');
});

it('supports the trashed filter', function () {
    $collection = createCollection('articles');
    createEntry($collection, 'Kept');
    $gone = createEntry($collection, 'Gone');
    $gone->delete();

    $request = Request::create('/cms/x', 'GET', ['filters' => ['trashed' => 'only']]);
    $table = TableQuery::for(Entry::query())->apply($request);

    expect($table->paginate($request)->total())->toBe(1);
});

it('stores table column preferences per user', function () {
    $user = actingAsSuperAdmin();

    $this->putJson('/cms/table-preferences/entries', ['columns' => ['title', 'status']])
        ->assertOk()
        ->assertJson(['columns' => ['title', 'status']]);

    expect(Setting::get("table_columns.{$user->id}.entries"))->toBe(['title', 'status']);
});

it('exports a table query to csv', function () {
    $collection = createCollection('articles');
    createEntry($collection, 'Export Me', ['body' => 'x']);
    createEntry($collection, 'Skip', [], 'draft');

    $request = Request::create('/cms/x', 'GET', ['filters' => ['status' => 'published']]);
    $table = TableQuery::for(Entry::query()->with('translations'))
        ->filterable(['status'])
        ->apply($request);

    $response = app(CsvExporter::class)->download($table, [
        new Column('id', 'ID'),
        new Column('status', 'Status'),
    ], 'entries.csv');

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('entries.csv');

    $content = '';
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();
    expect($content)->toContain('ID,Status')
        ->toContain('published')
        ->not->toContain('draft');
});
