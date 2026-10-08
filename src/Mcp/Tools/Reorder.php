<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Support\Reorder as ReorderAction;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Entry;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;
use Sunrice\Models\Term;

#[Description('Set the manual order of entries in a collection (shown in that order when the collection sorts by sort_order), terms in a taxonomy, or items in a menu. `ids` lists some or all of them in the new order: they swap into the positions they held, everything else stays put. Parents don\'t change (use update_entry parent_id, save_term parent_id or save_menu item.parent_id).')]
class Reorder extends SunriceTool
{
    protected string $name = 'reorder';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'type' => ['required', 'in:entries,terms,menu_items'],
            'in' => ['required'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);
        $ids = array_map('intval', $args['ids']);

        if ($args['type'] === 'entries') {
            $collection = $this->collection($args['in']);
            if ($collection === null) {
                return $this->notFound('Collection');
            }
            $this->authorize('reorder', [Entry::class, $collection->id]);
            $all = Entry::withTrashed()->where('collection_id', $collection->id)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
            $class = Entry::class;
            $event = 'entry_saved';
        } elseif ($args['type'] === 'terms') {
            $taxonomy = $this->taxonomy($args['in']);
            if ($taxonomy === null) {
                return $this->notFound('Taxonomy');
            }
            $this->authorize('reorder', [Term::class, $taxonomy->id]);
            $all = Term::withTrashed()->where('taxonomy_id', $taxonomy->id)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
            $class = Term::class;
            $event = 'term_saved';
        } else {
            $menu = $this->findByHandle(Menu::class, $args['in']);
            if ($menu === null) {
                return $this->notFound('Menu');
            }
            $this->authorize('update', $menu);
            $all = MenuItem::query()->where('menu_id', $menu->id)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
            $class = MenuItem::class;
            $event = 'menu_saved';
        }

        $all = array_map('intval', $all);
        $unknown = array_values(array_diff($ids, $all));
        if ($unknown !== []) {
            return Response::error('Not in this '.str_replace('_', ' ', rtrim($args['type'], 's')).' list: '.implode(', ', $unknown).'.');
        }

        // The listed ids take the slots they occupied, in the new order.
        $moved = array_values(array_unique($ids));
        $slots = array_keys(array_intersect($all, $moved));
        foreach ($slots as $i => $slot) {
            $all[$slot] = $moved[$i];
        }
        app(ReorderAction::class)->handle($class, array_combine(range(1, count($all)), $all));
        ContentChanged::dispatch($event);

        return $this->json(['saved' => true, 'order' => $all]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['entries', 'terms', 'menu_items'])->required(),
            'in' => $schema->string()->description('Collection, taxonomy or menu handle (or id).')->required(),
            'ids' => $schema->array()->items($schema->integer())->description('Ids in their new order.')->required(),
        ];
    }
}
