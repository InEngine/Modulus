<?php

namespace InEngine\Modulus\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \InEngine\Modulus\Modulus
 */
class Modulus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \InEngine\Modulus\Modulus::class;
    }
}
