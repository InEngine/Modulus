<?php

namespace InEngine\Modulus;

class Modulus {

    /**
     * getModuleName()
     * @return string
     *
     * Get the name of the Module
     */
    public function getModuleName():string
    {
        return config('modulus.name');
    }
}
