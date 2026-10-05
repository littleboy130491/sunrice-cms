<?php

declare(strict_types=1);

namespace Workbench\App\Seeders;

use Illuminate\Database\Seeder;
use Sunrice\Actions\Entries\PublishTranslation;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\Form;
use Sunrice\Models\GlobalSet;
use Sunrice\Models\Menu;
use Sunrice\Models\Setting;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;

/**
 * Demo content for the workbench host app (T15.3): two locales,
 * pages + articles collections, a hierarchical categories taxonomy,
 * a main menu, site globals, a contact form.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $blueprint = Blueprint::create([
            'handle' => 'page',
            'title' => 'Page',
            'fields' => [
                ['handle' => 'body', 'type' => 'rich_text', 'label' => 'Body'],
                ['handle' => 'sections', 'type' => 'flexible', 'label' => 'Sections', 'config' => ['fieldsets' => []]],
            ],
        ]);

        $articles = Collection::create([
            'handle' => 'articles', 'title' => 'Articles', 'blueprint_id' => $blueprint->id,
            'settings' => ['route' => '/articles/{slug}', 'archive_route' => '/articles', 'has_single' => true, 'has_archive' => true, 'translatable' => true, 'sluggable' => true],
        ]);

        $pages = Collection::create([
            'handle' => 'pages', 'title' => 'Pages', 'blueprint_id' => $blueprint->id,
            'settings' => ['route' => '/{slug}', 'has_single' => true, 'translatable' => true, 'sluggable' => true],
        ]);

        $categories = Taxonomy::create([
            'handle' => 'categories', 'title' => 'Categories',
            'settings' => ['hierarchical' => true, 'route' => '/category/{slug}', 'has_archive' => true],
        ]);
        $news = Term::create(['taxonomy_id' => $categories->id]);
        $news->translations()->create(['taxonomy_id' => $categories->id, 'locale' => Locales::main(), 'name' => 'News', 'slug' => 'news', 'data' => []]);

        $menu = Menu::create(['handle' => 'main', 'title' => 'Main menu']);

        $site = GlobalSet::create([
            'handle' => 'site', 'title' => 'Site', 'blueprint_id' => $blueprint->id, 'group' => 'global',
        ]);
        $site->values()->create(['locale' => null, 'data' => ['body' => 'Site tagline']]);
        $header = GlobalSet::create([
            'handle' => 'header', 'title' => 'Header', 'blueprint_id' => $blueprint->id, 'group' => 'template_part',
        ]);
        $header->values()->create(['locale' => null, 'data' => []]);
        $footer = GlobalSet::create([
            'handle' => 'footer', 'title' => 'Footer', 'blueprint_id' => $blueprint->id, 'group' => 'template_part',
        ]);
        $footer->values()->create(['locale' => null, 'data' => []]);

        Form::create([
            'handle' => 'contact', 'title' => 'Contact',
            'fields' => [
                ['handle' => 'name', 'type' => 'text', 'required' => true],
                ['handle' => 'email', 'type' => 'text', 'required' => true, 'validation' => ['email']],
                ['handle' => 'message', 'type' => 'textarea', 'required' => true],
            ],
            'settings' => ['success_message' => 'Thanks!'],
        ]);

        // A published homepage.
        $home = Entry::create(['collection_id' => $pages->id, 'blueprint_id' => $blueprint->id, 'status' => 'draft']);
        $t = $home->translations()->create([
            'collection_id' => $pages->id, 'locale' => Locales::main(),
            'title' => 'Home', 'slug' => 'home', 'data' => ['body' => '<p>Welcome</p>'], 'is_ready' => true,
        ]);
        app(PublishTranslation::class)->handle($t);
        Setting::set('homepage_entry_id', $home->id);

        $menu->items()->create(['type' => 'entry', 'target_id' => $home->id, 'labels' => [Locales::main() => 'Home', 'en' => 'Home'], 'sort_order' => 0]);
    }
}
