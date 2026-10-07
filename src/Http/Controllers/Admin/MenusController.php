<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Permissions\SyncPermissions;

class MenusController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Menu::class, 'menu');
    }

    public function index(): Response
    {
        return Inertia::render('Menus/Index', [
            'menus' => Menu::query()->orderBy('title')->get()
                ->map(fn (Menu $m) => [
                    'id' => $m->id,
                    'handle' => $m->handle,
                    'title' => $m->title,
                    'items_count' => $m->items()->count(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Menus/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'handle' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:sunrice_menus,handle'],
            'title' => ['required', 'string', 'max:255'],
        ]);

        $menu = Menu::create($validated);
        app(SyncPermissions::class)->handle();

        return redirect()->route('sunrice.admin.menus.edit', $menu)
            ->with('success', "Menu \"{$menu->title}\" created.");
    }

    public function edit(Menu $menu): Response
    {
        return Inertia::render('Menus/Edit', [
            'menu' => $menu->only('id', 'handle', 'title'),
            'items' => static::presentItems($menu->items()->orderBy('sort_order')->get()),
        ]);
    }

    /**
     * Menu items for the admin, with the title of what each links to.
     *
     * @param  \Illuminate\Support\Collection<int, MenuItem>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function presentItems(\Illuminate\Support\Collection $items): array
    {
        $ids = fn (string $type) => $items->where('type', $type)->pluck('target_id')->filter()->all();
        $entries = Entry::query()->with('translations')->whereIn('id', $ids('entry'))->get()->keyBy('id');
        $collections = Collection::query()->whereIn('id', $ids('collection'))->get()->keyBy('id');
        $terms = Term::query()->with(['translations', 'taxonomy'])->whereIn('id', $ids('term'))->get()->keyBy('id');

        return $items->map(fn (MenuItem $i) => [
            'id' => $i->id,
            'parent_id' => $i->parent_id,
            'type' => $i->type,
            'target_id' => $i->target_id,
            'target_title' => match ($i->type) {
                'entry' => $entries->get($i->target_id)?->mainTranslation()?->title,
                'collection' => $collections->get($i->target_id)?->title,
                'term' => ($t = $terms->get($i->target_id)) ? $t->taxonomy->title.': '.$t->mainTranslation()?->name : null,
                default => null,
            },
            // The term's taxonomy, so editing the item searches the right one.
            'target_taxonomy' => $i->type === 'term' ? $terms->get($i->target_id)?->taxonomy?->handle : null,
            'url' => $i->url,
            'labels' => $i->labels,
            'new_tab' => $i->new_tab,
        ])->values()->all();
    }

    /**
     * Link targets offered by the menu item editor.
     *
     * @return array{collections: mixed, taxonomies: mixed}
     */
    public static function itemOptions(): array
    {
        return [
            'collections' => Collection::query()->orderBy('sort_order')->orderBy('title')->get()->map(fn (Collection $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'has_archive' => (bool) $c->setting('has_archive', false),
            ])->values(),
            'taxonomies' => Taxonomy::query()->orderBy('title')->get(['id', 'handle', 'title']),
        ];
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $validated = $request->validate(['title' => ['required', 'string', 'max:255']]);
        $menu->update($validated);

        return back()->with('success', 'Menu saved.');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $menu->delete();
        app(SyncPermissions::class)->handle();
        ContentChanged::dispatch('menu_deleted');

        return redirect()->route('sunrice.admin.menus.index')
            ->with('success', "Menu \"{$menu->title}\" deleted.");
    }
}
