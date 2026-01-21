<?php

use Illuminate\Support\Collection;
use InEngine\Modulus\Facades\Modulus as ModulusFacade;
use InEngine\Modulus\Modulus;

beforeEach(function () {
    $this->modulus = new Modulus;
});

it('the class object has a module name', function () {
    expect($this->modulus->getModuleName())->toEqual('Modulus');
});

it('the class object has the module name modulus', function () {
    expect($this->modulus->getModuleName())->toEqual('Modulus');
});

it('has a module name when called statically', function () {
    expect(ModulusFacade::getModuleName())->toEqual('Modulus');
});

it('the class object has a Module FQN', function () {
    expect($this->modulus->getModuleFQN())->toEqual('InEngine\Modulus');
});

it('the class object has a Module FQN of InEngine\Modulus', function () {
    expect($this->modulus->getModuleFQN())->toEqual('InEngine\Modulus');
});

it('can access the Module FQN statically', function () {
    expect(ModulusFacade::getModuleFQN())->toEqual('InEngine\Modulus');
});

it('the class object has a Module Namespace', function () {
    expect($this->modulus->getModuleNamespace())->toEqual('InEngine\Modulus');
});

it('the class object has a Module Namespace of InEngine\Modulus', function () {
    expect($this->modulus->getModuleNamespace())->toEqual('InEngine\Modulus');
});

it('the class object can access the Module Namespace statically', function () {
    expect(ModulusFacade::getModuleNamespace())->toEqual('InEngine\Modulus');
});

it('the class object has modules listed in the modules configuration file', function () {
    expect($this->modulus->getAllModules(true))
        ->toBeArray()
        ->not->toBeEmpty();
});

it('the class object returns an array of modules listed in the modules configuration file', function () {
    expect($this->modulus->getAllModules(true))
        ->toBeArray()
        ->not->toBeEmpty()
        ->toEqual(config('modules'));
});

it('the class object returns an collection of modules listed in the modules configuration file', function () {
    expect($this->modulus->getAllModules())
        ->toBeInstanceOf(Collection::class);
});

it('the class object returns an collection of modules the same as listed in the modules configuration file', function () {
    expect($this->modulus->getAllModules()->toArray())
        ->toEqual(config('modules'));
});

it('the class object returns a non-empty collection of modules listed in the modules configuration file', function () {
    expect($this->modulus->getAllModules()->toArray())
        ->not->toBeEmpty();
});
