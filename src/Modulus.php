<?php

namespace InEngine\Modulus;

class Modulus
{
    /**
     * GetModuleName()
     *
     * @return string
     *
     * Get the name of the Module
     */
    public function getModuleName(): string
    {
        return config('modulus.name');
    }
}
