<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;

class MenuItemsController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Menu $menu): RedirectResponse
    {
        $this->authorize('update', $menu);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists('sunrice_menu_items', 'id')->where('menu_id', $menu->id)],
            'type' => ['required', 'string', Rule::in(['entry', 'collection', 'term', 'url'])],
            'target_id' => ['nullable', 'integer'],
            'url' => ['nullable', 'string', 'max:2048', 'required_if:type,url'],
            'labels' => ['required', 'array', 'min:1'],
            'labels.*' => ['nullable', 'string', 'max:255'],
            'new_tab' => ['boolean'],
        ]);

        $item = $menu->items()->make($validated);
        $item->sort_order = (int) $menu->items()->max('sort_order') + 1;
        $item->save();

        ContentChanged::dispatch('menu_saved');

        return back()->with('success', 'Item added.');
    }

    public function update(Request $request, MenuItem $item): RedirectResponse
    {
        $this->authorize('update', $item->menu);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists('sunrice_menu_items', 'id')->where('menu_id', $item->menu_id), 'not_in:'.$item->id],
            'type' => ['sometimes', 'string', Rule::in(['entry', 'collection', 'term', 'url'])],
            'target_id' => ['nullable', 'integer'],
            'url' => ['nullable', 'string', 'max:2048'],
            'labels' => ['sometimes', 'required', 'array', 'min:1'],
            'labels.*' => ['nullable', 'string', 'max:255'],
            'new_tab' => ['boolean'],
        ]);

        $item->update($validated);
        ContentChanged::dispatch('menu_saved');

        return back()->with('success', 'Item saved.');
    }

    public function destroy(MenuItem $item): RedirectResponse
    {
        $this->authorize('update', $item->menu);

        $item->children()->update(['parent_id' => $item->parent_id]);
        $item->delete();
        ContentChanged::dispatch('menu_saved');

        return back()->with('success', 'Item removed.');
    }

    public function reorder(Request $request, Menu $menu): RedirectResponse
    {
        $this->authorize('update', $menu);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', Rule::exists('sunrice_menu_items', 'id')->where('menu_id', $menu->id)],
            'items.*.parent_id' => ['nullable', 'integer', Rule::exists('sunrice_menu_items', 'id')->where('menu_id', $menu->id)],
        ]);

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
