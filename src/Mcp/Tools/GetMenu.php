<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Frontend\MenuBuilder;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;

#[IsReadOnly]
#[Description('Read a menu: its items as stored (id, parent_id, type url|entry|collection|term, target_id or url, labels per language, new_tab) and as the site renders it (labels and URLs resolved).')]
class GetMenu extends SunriceTool
{
    protected string $name = 'get_menu';

    public function handle(Request $request): Response
    {
        $menu = $this->findByHandle(Menu::class, $request->get('handle'));
        if ($menu === null) {
            return $this->notFound('Menu');
        }
        $this->authorize('view', $menu);

        return $this->json([
            'handle' => $menu->handle,
            'title' => $menu->title,
            'items' => $menu->items()->orderBy('sort_order')->get()->map(fn (MenuItem $i) => $i->only('id', 'parent_id', 'sort_order', 'type', 'target_id', 'url', 'labels', 'new_tab'))->all(),
            'rendered' => app(MenuBuilder::class)->build($menu->handle)->map->toArray()->all(),
            'template_usage' => "@foreach (sunrice_menu('{$menu->handle}') as $item) … $item->label, $item->url, $item->isActive, $item->children",
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['handle' => $schema->string()->required()];
    }
}
