<?php

namespace InEngine\Modulus\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \InEngine\Modulus\Modulus
 */
class Modulus extends Facade
{
    /**
     * Get the Accessor for the Module
     *
     * @static @public getFacadeAccessor()
     * @return string
     */
    public static function getFacadeAccessor(): string
    {
        return \InEngine\Modulus\Modulus::class;
    }
}
