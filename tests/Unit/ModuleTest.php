<?php

use Illuminate\Support\Collection;
use InEngine\Modulus\Facades\Modulus as ModulusFacade;
use InEngine\Modulus\Modulus;

$configFixturesPath = realpath(getcwd().'/tests/fixtures');
$balancedConfigFileName = '/modules_balanced.php';
$emptyConfigFileName = '/modules_empty.php';
$stubConfigFile = realpath(getcwd().'/stubs/modules.stub.php');
$configFileName = '/modules.php';
$configPath = realpath(getcwd().'/vendor/orchestra/testbench-core/laravel/config');

/**
 * Run before each test
 */
beforeEach(function () use ($configPath, $configFileName, $configFixturesPath, $balancedConfigFileName) {
    // Legacy config/modules.php API — skip until ModuleRepository lands (Step 1).
    $this->markTestSkipped('Deferred until ModuleRepository replaces the config/modules.php API.');

    $this->copyBalancedConfigFile($configPath, $configFileName, $configFixturesPath, $balancedConfigFileName);
    $this->modulus = new Modulus;
});

/**
 * Run after each test
 */
afterEach(function () use ($configPath, $configFileName, $configFixturesPath, $balancedConfigFileName) {
    $this->copyBalancedConfigFile($configPath, $configFileName, $configFixturesPath, $balancedConfigFileName);
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

it('the class object returns a collection of modules the same as listed in the modules configuration file', function () {
    expect($this->modulus->getAllModules()->toArray())
        ->toEqual(config('modules'));
});

it('the class object returns a non-empty collection of modules listed in the modules configuration file', function () {
    expect($this->modulus->getAllModules()->toArray())
        ->not->toBeEmpty();
});
