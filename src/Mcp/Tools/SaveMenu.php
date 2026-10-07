<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Menus\SaveMenuItem;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;
use Sunrice\Permissions\SyncPermissions;

#[Description('Create a menu (handle + title), and add, change or remove one item. An item: {type: "url"|"entry"|"collection"|"term", url (for url) or target_id (the entry/collection/term id), labels: {locale: text} (empty = the target\'s title), new_tab, parent_id (nest under another item)}. Send item.id to change that item (only the keys given), item.delete: true to remove it (its sub-items move up). New items go at the end.')]
class SaveMenu extends SunriceTool
{
    protected string $name = 'save_menu';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'handle' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'title' => ['nullable', 'string', 'max:255'],
            'item' => ['nullable', 'array'],
        ]);
        $menu = Menu::query()->where('handle', $args['handle'])->first();
        if ($menu === null) {
            $this->authorize('create', Menu::class);
            $menu = Menu::create(['handle' => $args['handle'], 'title' => $args['title'] ?? ucfirst($args['handle'])]);
            app(SyncPermissions::class)->handle();
        } else {
            $this->authorize('update', $menu);
            if (! empty($args['title']) && $args['title'] !== $menu->title) {
                $menu->update(['title' => $args['title']]);
                ContentChanged::dispatch('menu_saved');
            }
        }

        $item = (array) ($args['item'] ?? []);
        if ($item !== []) {
            $existing = isset($item['id']) ? MenuItem::query()->where('menu_id', $menu->id)->find((int) $item['id']) : null;
            if (isset($item['id']) && $existing === null) {
                return $this->notFound('Menu item');
            }
            if ($existing !== null && ! empty($item['delete'])) {
                $existing->children()->update(['parent_id' => $existing->parent_id]);
                $existing->delete();
                ContentChanged::dispatch('menu_saved');
            } else {
                unset($item['id'], $item['delete']);
                app(SaveMenuItem::class)->handle($menu, $item, $existing);
            }
        }

        return $this->json([
            'saved' => true,
            'handle' => $menu->handle,
            'items' => $menu->items()->orderBy('sort_order')->get()->map(fn (MenuItem $i) => $i->only('id', 'parent_id', 'type', 'target_id', 'url', 'labels', 'new_tab'))->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'handle' => $schema->string()->required(),
            'title' => $schema->string(),
            'item' => $schema->object()->description('One item to add, change (with id) or remove (id + delete: true).'),
        ];
    }
}
