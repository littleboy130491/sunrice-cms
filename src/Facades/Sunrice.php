<?php

declare(strict_types=1);

namespace Sunrice\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Sunrice\Sunrice
 */
class Sunrice extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sunrice\Sunrice::class;
    }
}
