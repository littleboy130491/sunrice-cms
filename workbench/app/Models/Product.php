<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $title
 * @property string $sku
 * @property float $price
 * @property bool $active
 * @property int|null $owner_id
 */
class Product extends Model
{
    protected $guarded = [];

    protected $casts = ['price' => 'float', 'active' => 'boolean'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
