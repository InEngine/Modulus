<?php

namespace InEngine\Modulus;

class Modulus
{
    public function getModuleName(): string
    {
        return config('modulus.name');
    }
}
