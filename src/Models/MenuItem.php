<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property int $sort_order
 * @property string $type
 * @property int|null $target_id
 * @property string|null $url
 * @property array<string,string> $labels
 * @property bool $new_tab
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MenuItem extends Model
{
    protected $table = 'sunrice_menu_items';

    protected $guarded = [];

    protected $attributes = ['labels' => '{}'];

    protected $casts = [
        'labels' => 'array',
        'new_tab' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /** @return BelongsTo<MenuItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /** @return HasMany<MenuItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }
}
