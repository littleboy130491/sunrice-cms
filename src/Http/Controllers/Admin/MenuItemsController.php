<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Menus\SaveMenuItem;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;

class MenuItemsController extends Controller
{
    use AuthorizesRequests;

    public function create(Request $request, Menu $menu): Response
    {
        $this->authorize('update', $menu);
        $parent = $request->integer('parent') ?: null;
        $parentItem = $parent === null ? null : $menu->items()->whereKey($parent)->first();

        return $this->editor($menu, null, $parentItem);
    }

    /** The editor's older address (/menu-items/{id}/edit). */
    public function legacyEdit(MenuItem $item): RedirectResponse
    {
        return redirect()->route('sunrice.admin.menu-items.edit', [$item->menu_id, $item], 301);
    }

    public function edit(Menu $menu, MenuItem $item): Response|RedirectResponse
    {
        $this->authorize('update', $item->menu);
        // Opened under another menu's address: go to its own.
        if ($item->menu_id !== $menu->id) {
            return redirect()->route('sunrice.admin.menu-items.edit', [$item->menu_id, $item]);
        }

        return $this->editor($item->menu, $item, $item->parent_id === null ? null : MenuItem::query()->find($item->parent_id));
    }

    /** The menu item editor page (add and edit). */
    protected function editor(Menu $menu, ?MenuItem $item, ?MenuItem $parent): Response
    {
        $present = fn (MenuItem $i) => MenusController::presentItems(collect([$i]))[0];

        return Inertia::render('Menus/ItemEdit', [
            'menu' => $menu->only('id', 'handle', 'title'),
            'item' => $item === null ? null : $present($item),
            'parent' => $parent === null ? null : $present($parent),
        ] + MenusController::itemOptions());
    }

    public function store(Request $request, Menu $menu, SaveMenuItem $save): RedirectResponse
    {
        $this->authorize('update', $menu);

        $save->handle($menu, $request->all());

        return redirect()->route('sunrice.admin.menus.edit', $menu)->with('success', 'Item added.');
    }

    public function update(Request $request, MenuItem $item, SaveMenuItem $save): RedirectResponse
    {
        $this->authorize('update', $item->menu);

        $save->handle($item->menu, $request->all(), $item);

        return redirect()->route('sunrice.admin.menus.edit', $item->menu_id)->with('success', 'Item saved.');
    }

    public function destroy(MenuItem $item): RedirectResponse
    {
        $this->authorize('update', $item->menu);

        $item->children()->update(['parent_id' => $item->parent_id]);
        $item->delete();
        ContentChanged::dispatch('menu_saved');

        return redirect()->route('sunrice.admin.menus.edit', $item->menu_id)->with('success', 'Item removed.');
    }

    public function reorder(Request $request, Menu $menu): RedirectResponse
    {
        $this->authorize('update', $menu);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', Rule::exists('sunrice_menu_items', 'id')->where('menu_id', $menu->id)],
            'items.*.parent_id' => ['nullable', 'integer', Rule::exists('sunrice_menu_items', 'id')->where('menu_id', $menu->id)],
        ]);

        $parents = MenuItem::query()->where('menu_id', $menu->id)->pluck('parent_id', 'id')->all();
        foreach ($validated['items'] as $row) {
            $parents[$row['id']] = $row['parent_id'] ?? null;
        }
        if (SaveMenuItem::hasCycle($parents)) {
            throw ValidationException::withMessages(['items' => 'An item can\'t be placed under one of its own sub-items.']);
        }

        foreach ($validated['items'] as $index => $row) {
            MenuItem::where('id', $row['id'])->update([
                'parent_id' => $row['parent_id'] ?? null,
                'sort_order' => $index + 1,
            ]);
        }

        ContentChanged::dispatch('menu_saved');

        return back();
    }
}
