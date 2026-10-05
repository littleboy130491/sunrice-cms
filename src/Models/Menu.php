<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Sunrice\Database\Factories\MenuFactory;

/**
 * @property int $id
 * @property string $handle
 * @property string $title
 * @property array<string,mixed> $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @use HasFactory<MenuFactory>
 */
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    protected $table = 'sunrice_menus';

    protected $guarded = [];

    protected static function newFactory(): MenuFactory
    {
        return MenuFactory::new();
    }

    /** @return HasMany<MenuItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }

    /** @return HasMany<MenuItem, $this> */
    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }
}
