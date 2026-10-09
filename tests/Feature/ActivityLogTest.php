<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Sunrice\Actions\Forms\SaveForm;
use Sunrice\Activity\ActivityLogger;
use Sunrice\Models\ActivityLog;
use Sunrice\Models\Entry;
use Sunrice\Models\Setting;
use Sunrice\Permissions\SyncPermissions;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;
use Workbench\App\Sunrice\ProductResource;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/** @return array<int, array{0: string, 1: string, 2: string|null}> action, type, label of each line, oldest first */
function activityLines(): array
{
    return ActivityLog::query()->orderBy('id')->get()
        ->map(fn (ActivityLog $l) => [$l->action, $l->subject_type, $l->subject_label])->all();
}

beforeEach(function () {
    $this->admin = actingAsSuperAdmin();
    ActivityLog::query()->delete();
});

it('logs an entry\'s life in the admin as one line per change', function () {
    $collection = createCollection('pages');
    $collection->update(['title' => 'Pages']);
    ActivityLog::query()->delete();

    post('/cms/collections/pages/entries', ['title' => 'About', 'data' => ['body' => 'Hello']])->assertRedirect();
    $entry = Entry::query()->firstOrFail();

    put("/cms/entries/{$entry->id}", ['locale' => 'id', 'title' => 'About us', 'slug' => 'about', 'data' => ['body' => 'Updated']])->assertRedirect();
    post("/cms/entries/{$entry->id}/publish", ['locale' => 'id'])->assertRedirect();
    delete("/cms/entries/{$entry->id}")->assertRedirect();
    post("/cms/entries/{$entry->id}/restore")->assertRedirect();

    expect(activityLines())->toBe([
        ['created', 'entry', 'About (Pages)'],
        ['updated', 'entry', 'About (Pages)'],
        ['published', 'entry', 'About us (Pages)'],
        ['trashed', 'entry', 'About us (Pages)'],
        ['restored', 'entry', 'About us (Pages)'],
    ]);

    $update = ActivityLog::query()->where('action', 'updated')->firstOrFail();
    expect($update->user_id)->toBe($this->admin->id)
        ->and($update->user_name)->toBe('Admin')
        ->and($update->properties['changes'])->toBe(['title (id, draft)', 'data (id, draft)']);
    expect(ActivityLog::query()->where('action', 'published')->first()->properties['changes'])
        ->toBe(['published_at', 'title (id)', 'data.body (id)']);
});

it('logs site settings with the fields that changed, but not table preferences', function () {
    Setting::set('site', ['name' => 'Old', 'tagline' => 'Hi']);
    app(ActivityLogger::class)->reset(); // the next request
    Setting::set('site', ['name' => 'New', 'tagline' => 'Hi']);
    app(ActivityLogger::class)->reset();
    Setting::set('table_per_page.1.entries', 50);
    Setting::set('table_columns.1.entries', ['title']);

    $lines = ActivityLog::query()->orderBy('id')->get();
    expect($lines)->toHaveCount(2)
        ->and($lines->pluck('subject_label')->unique()->all())->toBe(['Site settings'])
        ->and($lines->pluck('action')->unique()->all())->toBe(['updated'])
        ->and($lines[0]->properties['changes'])->toBe(['name', 'tagline'])
        ->and($lines[1]->properties['changes'])->toBe(['name']);
});

it('logs reordering, role permissions and resources', function () {
    $collection = createCollection('pages', ['sortable' => true]);
    $a = createEntry($collection, 'A');
    $b = createEntry($collection, 'B');
    ActivityLog::query()->delete();
    app(ActivityLogger::class)->reset();

    post('/cms/collections/pages/entries/reorder', ['items' => [$b->id, $a->id]]);
    expect(activityLines())->toBe([['reordered', 'collection', 'Pages']]);
    ActivityLog::query()->delete();

    post('/cms/roles', ['name' => 'Reviewer', 'permissions' => ['sunrice.access-admin']])->assertRedirect();
    $role = Role::findByName('Reviewer');
    put("/cms/roles/{$role->id}", ['permissions' => ['sunrice.access-admin', 'sunrice.view-drafts']])->assertRedirect();
    put("/cms/roles/{$role->id}", ['permissions' => ['sunrice.access-admin', 'sunrice.view-drafts']])->assertRedirect(); // no change
    expect(activityLines())->toBe([['created', 'role', 'Reviewer'], ['updated', 'role', 'Reviewer']])
        ->and(ActivityLog::query()->latest('id')->first()->properties['changes'])->toBe(['permissions']);
    ActivityLog::query()->delete();

    app(Sunrice\Sunrice::class)->registerResource(ProductResource::class);
    post('/cms/resources/products', ['title' => 'Kettle', 'sku' => 'K-1', 'price' => 10, 'active' => true])->assertRedirect();
    $product = Product::query()->firstOrFail();
    put("/cms/resources/products/{$product->id}", ['title' => 'Kettle Pro', 'sku' => 'K-1', 'price' => 12, 'active' => true]);
    delete("/cms/resources/products/{$product->id}");
    expect(activityLines())->toBe([['created', 'product', 'Kettle'], ['updated', 'product', 'Kettle Pro'], ['deleted', 'product', 'Kettle Pro']])
        ->and(ActivityLog::query()->where('action', 'updated')->first()->properties['changes'])->toBe(['title', 'price']);
});

it('marks console work as System and skips visitors', function () {
    auth()->forgetGuards();
    $collection = createCollection('pages');
    // A command / queue job: no route, no user.
    expect(ActivityLog::query()->where('subject_type', 'collection')->first()?->user_name)->toBeNull()
        ->and(ActivityLog::query()->where('subject_type', 'collection')->exists())->toBeTrue();

    ActivityLog::query()->delete();
    $form = app(SaveForm::class)->handle(null, ['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'name', 'type' => 'text']]]);
    ActivityLog::query()->delete();
    config()->set('honeypot.enabled', false);
    post('/sunrice/forms/contact', ['data' => ['name' => 'Visitor']])->assertRedirect();

    expect(ActivityLog::query()->count())->toBe(0);
});

it('can be paused and switched off', function () {
    app(ActivityLogger::class)->withoutLogging(fn () => createCollection('quiet'));
    expect(ActivityLog::query()->count())->toBe(0);

    config()->set('sunrice.activity.enabled', false);
    createCollection('off');
    expect(ActivityLog::query()->count())->toBe(0);
});

it('lists and filters the log for users who may see it', function () {
    createCollection('pages');
    app(ActivityLogger::class)->reset();
    Setting::set('site', ['name' => 'X']);
    app(ActivityLogger::class)->record('pruned', 'Activity log', ['days' => 30, 'deleted' => 2]);

    get('/cms/activity')->assertInertia(fn (Assert $page) => $page
        ->component('Activity/Index')
        ->where('rows.data.0.activity', 'Pruned activity log')
        ->where('rows.data.0.details', '2 older than 30 days')
        ->where('rows.data.1.activity', 'Updated setting “Site settings”')
        ->where('rows.data.1.details', 'name')
        ->where('rows.data.2.user', 'Admin')
        ->where('can.prune', true)
        ->where('pruneDays', 180)
        ->where('filters', fn ($filters) => collect($filters)->pluck('key')->all() === ['period', 'action', 'subject_type', 'user']));

    get('/cms/activity?filters[subject_type]=collection')->assertInertia(fn (Assert $page) => $page
        ->has('rows.data', 1)
        ->where('rows.data.0.activity', 'Created collection “Pages”'));
    get('/cms/activity?filters[action]=pruned&search=activity')->assertInertia(fn (Assert $page) => $page->has('rows.data', 1));
    get('/cms/activity?filters[user]=system')->assertInertia(fn (Assert $page) => $page->has('rows.data', 0));

    $editor = User::query()->create(['name' => 'Ed', 'email' => 'ed@example.com', 'password' => 'x']);
    app(SyncPermissions::class)->handle();
    $editor->givePermissionTo('sunrice.access-admin');
    $this->actingAs($editor);
    get('/cms/activity')->assertForbidden();
    post('/cms/activity/prune', ['days' => 0])->assertForbidden();

    $editor->givePermissionTo('sunrice.activity.view');
    get('/cms/activity')->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.prune', false));
});

it('prunes old entries from the admin and the command line', function () {
    $line = fn (int $daysAgo) => ActivityLog::query()->create(['action' => 'updated', 'subject_type' => 'entry', 'subject_label' => "{$daysAgo} days", 'created_at' => now()->subDays($daysAgo)]);
    $line(400);
    $line(200);
    $line(100);
    $line(10);

    post('/cms/activity/prune', ['days' => 'x'])->assertSessionHasErrors('days');
    post('/cms/activity/prune', ['days' => 150])->assertSessionHas('success', 'Deleted 2 entries older than 150 days.');
    expect(ActivityLog::query()->pluck('subject_label')->all())->toBe(['100 days', '10 days', 'Activity log'])
        ->and(ActivityLog::query()->where('action', 'pruned')->first()->user_name)->toBe('Admin');

    $line(181);
    auth()->forgetGuards();
    expect(Artisan::call('sunrice:prune-activity'))->toBe(0); // default: 180 days
    expect(ActivityLog::query()->where('subject_label', '181 days')->exists())->toBeFalse()
        ->and(ActivityLog::query()->where('subject_label', '100 days')->exists())->toBeTrue();

    Artisan::call('sunrice:prune-activity', ['--days' => 30]);
    expect(Artisan::output())->toContain('Deleted 1 activity log entry older than 30 days.');
    expect(ActivityLog::query()->where('subject_label', '100 days')->exists())->toBeFalse()
        ->and(ActivityLog::query()->where('subject_label', '10 days')->exists())->toBeTrue();

    expect(Artisan::call('sunrice:prune-activity', ['--days' => 'soon']))->toBe(1);
});
