<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Model;

class Reference extends Model
{
    protected $table = 'sunrice_references';

    public $timestamps = false;

    protected $guarded = [];
}
