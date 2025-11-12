<?php

namespace InEngine\Modulus\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \InEngine\Modulus\Modulus
 */
class Modulus extends Facade
{
    /**
     * @static @public GetModuleName()
     * @return string
     *
     * Get the name of the Module
     */
    public static function getFacadeAccessor(): string
    {
        return \InEngine\Modulus\Modulus::class;
    }
}
