<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Database\Factories\AssetFactory;

/**
 * @use HasFactory<AssetFactory>
 */
class TestModel extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;
}
