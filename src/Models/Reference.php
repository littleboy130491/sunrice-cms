<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $source_type
 * @property int $source_id
 * @property string|null $field_path
 * @property string $target_type
 * @property int $target_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Reference extends Model
{
    protected $table = 'sunrice_references';

    public $timestamps = false;

    protected $guarded = [];
}
