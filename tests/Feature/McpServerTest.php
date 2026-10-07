<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Mcp\SunriceServer;
use Sunrice\Mcp\Tools;
use Sunrice\Models\ApiToken;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Entry;
use Sunrice\Models\Menu;
use Sunrice\Models\Taxonomy;
use Sunrice\Permissions\SyncPermissions;
use Workbench\App\Models\User;

use function Pest\Laravel\postJson;

/** The JSON a tool returned (or its error text under "error"). */
function mcpResult(TestResponse $response): array
{
    $property = new ReflectionProperty(TestResponse::class, 'response');
    $result = $property->getValue($response)->toArray()['result'] ?? [];
    $text = (string) ($result['content'][0]['text'] ?? '');
    if ($result['isError'] ?? false) {
        return ['error' => $text];
    }

    return json_decode($text, true) ?? ['text' => $text];
}

beforeEach(function () {
    $this->admin = actingAsSuperAdmin();
    $this->pages = createCollection('pages', ['route' => '/{slug}', 'hierarchical' => true]);
    RouteMatcher::flush();
});

it('describes the site', function () {
    $info = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\GetSiteInfo::class));

    expect($info['languages']['main'])->toBe('id')
        ->and(collect($info['collections'])->pluck('handle')->all())->toContain('pages')
        ->and($info['field_types'])->toContain('rich_text')
        ->and($info['user']['super_admin'])->toBeTrue();
});

it('creates, edits, translates and publishes entries', function () {
    $created = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\CreateEntry::class, [
        'collection' => 'pages', 'title' => 'About us', 'data' => ['body' => 'Hello'], 'seo' => ['description' => 'Who we are'],
    ]));
    expect($created['status'])->toBe('draft')->and($created['translations']['id']['draft']['data']['body'])->toBe('Hello');
    $id = $created['id'];

    // Partial edit keeps the rest of the draft.
    $updated = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\UpdateEntry::class, ['id' => $id, 'title' => 'About', 'publish' => true]));
    expect($updated['status'])->toBe('published')
        ->and($updated['translations']['id']['title'])->toBe('About')
        ->and($updated['translations']['id']['data']['body'])->toBe('Hello')
        ->and($updated['translations']['id']['seo']['description'])->toBe('Who we are');

    $english = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\UpdateEntry::class, ['id' => $id, 'locale' => 'en', 'title' => 'About (EN)', 'slug' => 'about-en', 'publish' => true]));
    expect($english['translations']['en']['ready'])->toBeTrue()
        ->and($english['translations']['en']['url'])->toBe('/en/about-en');

    $child = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\CreateEntry::class, ['collection' => 'pages', 'title' => 'Team', 'parent_id' => $id, 'publish' => true]));
    expect($child['parent_id'])->toBe($id);

    $list = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\ListEntries::class, ['collection' => 'pages', 'search' => 'Team']));
    expect($list['total'])->toBe(1)->and($list['entries'][0]['url'])->toEndWith('/team');

    mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\ManageEntry::class, ['id' => $child['id'], 'action' => 'trash']));
    expect(Entry::query()->find($child['id']))->toBeNull();
    expect(mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\ManageEntry::class, ['id' => $child['id'], 'action' => 'delete'])))->toHaveKey('deleted');
});

it('reports validation errors instead of saving', function () {
    $response = SunriceServer::actingAs($this->admin)->tool(Tools\CreateEntry::class, ['collection' => 'pages']);

    $response->assertHasErrors();
});

it('builds structure: blueprints, collections, taxonomies, terms, globals and menus', function () {
    mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\SaveBlueprint::class, ['handle' => 'product', 'title' => 'Product', 'fields' => [
        ['handle' => 'price', 'type' => 'number', 'label' => 'Price'],
    ]]));
    $collection = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\SaveCollection::class, [
        'handle' => 'products', 'title' => 'Products', 'blueprint' => 'product', 'settings' => ['route' => 'shop', 'has_archive' => true],
    ]));
    expect($collection['entry_url_pattern'])->toBe('/shop/{slug}')->and($collection['blueprint'])->toBe('product');

    mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\SaveTaxonomy::class, ['handle' => 'brands', 'title' => 'Brands', 'collections' => ['products']]));
    $term = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\SaveTerm::class, ['taxonomy' => 'brands', 'translations' => ['id' => ['name' => 'Acme']]]));
    expect($term['translations']['id']['slug'])->toBe('acme');
    $renamed = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\SaveTerm::class, ['id' => $term['id'], 'translations' => ['en' => ['name' => 'Acme EN']]]));
    expect($renamed['translations'])->toHaveKeys(['id', 'en']);

    $blueprint = Blueprint::query()->where('handle', 'product')->first();
    $global = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\SaveGlobal::class, ['handle' => 'shop', 'blueprint' => 'product', 'values' => ['price' => 3]]));
    expect($global['created'])->toBeTrue()->and($global['values']['price'])->toBe(3);

    $page = createEntry($this->pages, 'Home');
    $menu = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\SaveMenu::class, ['handle' => 'main', 'title' => 'Main', 'item' => ['type' => 'entry', 'target_id' => $page->id]]));
    expect($menu['items'])->toHaveCount(1);
    $itemId = $menu['items'][0]['id'];
    mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\SaveMenu::class, ['handle' => 'main', 'item' => ['id' => $itemId, 'labels' => ['id' => 'Beranda']]]));
    expect(Menu::query()->first()->items()->first()->labels)->toBe(['id' => 'Beranda']);

    $deleted = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\DeleteStructure::class, ['type' => 'taxonomy', 'handle' => 'brands']));
    expect($deleted['deleted'])->toBeTrue()->and(Taxonomy::query()->where('handle', 'brands')->exists())->toBeFalse();
    expect($blueprint)->not->toBeNull();
});

it('audits SEO', function () {
    foreach (['Hi', 'Hey'] as $title) {
        createEntry($this->pages, $title)->translations()->first()->update(['seo' => ['title' => 'Same title']]);
    }

    $audit = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\SeoAudit::class, ['locale' => 'id']));

    expect($audit['checked_entries'])->toBe(2)
        ->and($audit['issues'][0]['issues'])->toContain('No meta description.')
        ->and(collect($audit['duplicates'])->pluck('kind')->all())->toContain('meta title');
});

it('guides, writes and renders templates', function () {
    $guide = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\GetTemplateGuide::class));
    expect($guide['guide'])->toContain('first existing view wins')
        ->and($guide['current_template_map']['collections']['pages']['entry_page']['candidates'][0]['view'])->toBe('sunrice.pages.show')
        ->and($guide['can_write_templates'])->toBeTrue();

    $starter = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\ReadTemplate::class, ['source' => 'starter', 'path' => 'layouts/app.blade.php']));
    expect($starter['content'])->toContain('<x-sunrice::seo');

    $bad = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\WriteTemplate::class, ['path' => 'pages/show.blade.php', 'content' => '@if($x) <p>']));
    expect($bad['error'])->toContain('syntax error');
    expect(mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\WriteTemplate::class, ['path' => '../evil.php', 'content' => 'x'])))->toHaveKey('error');

    $written = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\WriteTemplate::class, ['path' => 'pages/show.blade.php', 'content' => '<h1>Custom: {{ $entry->title }}</h1>']));
    expect($written['view'])->toBe('sunrice.pages.show');

    $entry = createEntry($this->pages, 'Hello there');
    $page = mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\RenderPage::class, ['path' => '/'.$entry->translations()->first()->slug]));
    expect($page['status'])->toBe(200)->and($page['html'])->toContain('Custom: Hello there');

    mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\WriteTemplate::class, ['path' => 'pages/show.blade.php', 'delete' => true]));
    expect(is_file(resource_path('views/sunrice/pages/show.blade.php')))->toBeFalse();
})->after(fn () => File::deleteDirectory(resource_path('views/sunrice/pages')));

it('keeps template writing and commands to those allowed', function () {
    app(SyncPermissions::class)->handle();
    $editor = User::query()->create(['name' => 'Ed', 'email' => 'ed@example.com', 'password' => bcrypt('x')]);
    $role = Role::findOrCreate('Agent', config('sunrice.auth.guard', 'web'));
    $role->givePermissionTo(['sunrice.ai-access', 'sunrice.access-admin']);
    $editor->assignRole($role);

    expect(mcpResult(SunriceServer::actingAs($editor)->tool(Tools\WriteTemplate::class, ['path' => 'x.blade.php', 'content' => 'x'])))->toHaveKey('error')
        ->and(mcpResult(SunriceServer::actingAs($editor)->tool(Tools\RunCommand::class, ['command' => 'cache:clear'])))->toHaveKey('error')
        ->and(mcpResult(SunriceServer::actingAs($editor)->tool(Tools\CreateEntry::class, ['collection' => 'pages', 'title' => 'Nope'])))->toHaveKey('error');

    expect(mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\RunCommand::class, ['command' => 'migrate:fresh']))['error'])->toContain('allowed commands');
    expect(mcpResult(SunriceServer::actingAs($this->admin)->tool(Tools\RunCommand::class, ['command' => 'view:clear']))['exit_code'])->toBe(0);
});

it('serves the HTTP endpoint only with a valid token', function () {
    $initialize = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']]];

    postJson('/mcp', $initialize)->assertUnauthorized();
    postJson('/mcp', $initialize, ['Authorization' => 'Bearer sr_wrong'])->assertUnauthorized();

    [, $plain] = ApiToken::issue($this->admin->id, 'Test');
    postJson('/mcp', $initialize, ['Authorization' => "Bearer {$plain}"])->assertOk()->assertJsonPath('result.serverInfo.name', 'Sunrice CMS');
    $tools = postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], ['Authorization' => "Bearer {$plain}"])->assertOk()->json('result.tools');
    expect(collect($tools)->pluck('name')->all())->toContain('get_site_info', 'update_entry', 'write_template');

    $call = postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'get_site_info', 'arguments' => (object) []]], ['Authorization' => "Bearer {$plain}"])->assertOk();
    expect(json_decode($call->json('result.content.0.text'), true)['user']['email'])->toBe('admin@example.com');
});

it('manages tokens in the admin and from the command line', function () {
    \Pest\Laravel\post('/cms/ai-access', ['name' => 'Laptop'])->assertSessionHas('sunrice_new_token');
    $token = ApiToken::query()->firstOrFail();
    expect($token->token)->toHaveLength(64);

    \Pest\Laravel\get('/cms/ai-access')->assertOk();
    \Pest\Laravel\delete("/cms/ai-access/{$token->id}");
    expect(ApiToken::query()->count())->toBe(0);

    \Pest\Laravel\artisan('sunrice:mcp-token', ['email' => 'admin@example.com', '--name' => 'CLI'])->assertSuccessful();
    expect(ApiToken::query()->where('name', 'CLI')->exists())->toBeTrue();
});
