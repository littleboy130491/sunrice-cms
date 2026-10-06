<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Closure;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;

class MenuItemsController extends Controller
{
    use AuthorizesRequests;

    /** Item type => table its target_id points at (null: a plain URL). */
    protected const TARGETS = [
        'url' => null,
        'entry' => 'sunrice_entries',
        'collection' => 'sunrice_collections',
        'term' => 'sunrice_terms',
    ];

    /**
     * The target must exist in the table of the chosen type.
     */
    protected function targetExists(Request $request): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($request): void {
            $table = self::TARGETS[$request->input('type')] ?? null;
            if ($table !== null && $value !== null && ! DB::table($table)->where('id', $value)->exists()) {
                $fail('The selected link target does not exist.');
            }
        };
    }

    /**
     * Keep only the field that matters for the type: a URL for links, a
     * target for everything else. Empty labels fall back to the target's
     * title on the site.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function clean(array $validated): array
    {
        if (isset($validated['type'])) {
            if ($validated['type'] === 'url') {
                $validated['target_id'] = null;
            } else {
                $validated['url'] = null;
            }
        }
        if (array_key_exists('labels', $validated)) {
            $validated['labels'] = array_filter((array) $validated['labels'], fn ($label) => is_string($label) && trim($label) !== '');
        }

        return $validated;
    }

    public function store(Request $request, Menu $menu): RedirectResponse
    {
        $this->authorize('update', $menu);

        $validated = $this->clean($request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists('sunrice_menu_items', 'id')->where('menu_id', $menu->id)],
            'type' => ['required', 'string', Rule::in(array_keys(self::TARGETS))],
            'target_id' => ['nullable', 'integer', 'required_unless:type,url', $this->targetExists($request)],
            'url' => ['nullable', 'string', 'max:2048', 'required_if:type,url'],
            'labels' => ['array'],
            'labels.*' => ['nullable', 'string', 'max:255'],
            'new_tab' => ['boolean'],
        ]));

        $item = $menu->items()->make($validated);
        $item->sort_order = (int) $menu->items()->max('sort_order') + 1;
        $item->save();

        ContentChanged::dispatch('menu_saved');

        return back()->with('success', 'Item added.');
    }

    public function update(Request $request, MenuItem $item): RedirectResponse
    {
        $this->authorize('update', $item->menu);

        $request->mergeIfMissing(['type' => $item->type]);
        $validated = $this->clean($request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists('sunrice_menu_items', 'id')->where('menu_id', $item->menu_id), 'not_in:'.$item->id],
            'type' => ['required', 'string', Rule::in(array_keys(self::TARGETS))],
            // Switching type (or an item without one) needs the new target/URL.
            'target_id' => ['nullable', 'integer', $this->targetExists($request), Rule::requiredIf(fn () => $request->input('type') !== 'url'
                && ($request->input('type') !== $item->type || $item->target_id === null))],
            'url' => ['nullable', 'string', 'max:2048', Rule::requiredIf(fn () => $request->input('type') === 'url'
                && ($item->type !== 'url' || $item->url === null))],
            'labels' => ['sometimes', 'array'],
            'labels.*' => ['nullable', 'string', 'max:255'],
            'new_tab' => ['boolean'],
        ]));

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
