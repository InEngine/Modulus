<?php

use InEngine\Modulus\Modulus;
use InEngine\Modulus\Facades\Modulus as ModulusFacade;


it('has a module name', function () {
    $modulus = new Modulus();
    expect($modulus->getModuleName())->toEqual(config('modulus.name'));
});

it('has the module name modulus', function () {
    $modulus = new Modulus();
    expect($modulus->getModuleName())->toEqual(config('modulus.name'));
});

it('has a module name when called statically', function () {
    expect(ModulusFacade::getModuleName())->toEqual(config('modulus.name'));
});
