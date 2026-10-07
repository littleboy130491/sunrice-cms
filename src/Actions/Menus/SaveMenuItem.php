<?php

declare(strict_types=1);

namespace Sunrice\Actions\Menus;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;
use Sunrice\Support\SafeUrl;

/**
 * Adds a menu item, or updates one (only the keys given). Items link to
 * a URL or to an entry, collection or term by id.
 */
class SaveMenuItem
{
    /** Item type => table its target_id points at (null: a plain URL). */
    public const TARGETS = [
        'url' => null,
        'entry' => 'sunrice_entries',
        'collection' => 'sunrice_collections',
        'term' => 'sunrice_terms',
    ];

    /**
     * @param  array<string, mixed>  $input  parent_id, type, target_id, url, labels {locale: label}, new_tab
     *
     * @throws ValidationException
     */
    public function handle(Menu $menu, array $input, ?MenuItem $item = null): MenuItem
    {
        if ($item !== null) {
            $input += ['type' => $item->type];
        }
        $type = $input['type'] ?? null;

        $validated = $this->clean(validator($input, [
            'parent_id' => array_filter([
                'nullable', 'integer', Rule::exists('sunrice_menu_items', 'id')->where('menu_id', $menu->id),
                $item === null ? null : 'not_in:'.$item->id,
            ]),
            'type' => ['required', 'string', Rule::in(array_keys(self::TARGETS))],
            // A new item, or a change of type, needs the new target/URL.
            'target_id' => ['nullable', 'integer', $this->targetExists($type), Rule::requiredIf(fn () => $type !== 'url'
                && ($item === null || $type !== $item->type || $item->target_id === null))],
            'url' => ['nullable', 'string', 'max:2048', SafeUrl::rule(), Rule::requiredIf(fn () => $type === 'url'
                && ($item === null || $item->type !== 'url' || $item->url === null))],
            'labels' => [$item === null ? 'nullable' : 'sometimes', 'array'],
            'labels.*' => ['nullable', 'string', 'max:255'],
            'new_tab' => ['boolean'],
        ])->validate());

        if ($item === null) {
            $item = $menu->items()->make($validated);
            $item->sort_order = (int) $menu->items()->max('sort_order') + 1;
            $item->save();
        } else {
            if (array_key_exists('parent_id', $validated)) {
                $parents = MenuItem::query()->where('menu_id', $item->menu_id)->pluck('parent_id', 'id')->all();
                $parents[$item->id] = $validated['parent_id'];
                if (static::hasCycle($parents)) {
                    throw ValidationException::withMessages(['parent_id' => 'An item can\'t be placed under one of its own sub-items.']);
                }
            }
            $item->update($validated);
        }

        ContentChanged::dispatch('menu_saved');

        return $item;
    }

    /**
     * Whether following parents from any item leads back to it (which
     * would hide that branch from the menu).
     *
     * @param  array<mixed>  $parents  item id => parent id
     */
    public static function hasCycle(array $parents): bool
    {
        foreach (array_keys($parents) as $id) {
            $seen = [];
            for ($current = $id; $current !== null; $current = $parents[$current] ?? null) {
                if (isset($seen[$current])) {
                    return true;
                }
                $seen[$current] = true;
            }
        }

        return false;
    }

    /** The target must exist in the table of the chosen type. */
    protected function targetExists(mixed $type): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($type): void {
            $table = is_string($type) ? (self::TARGETS[$type] ?? null) : null;
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
}
