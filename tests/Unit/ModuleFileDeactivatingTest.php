<?php

use Illuminate\Support\Collection;
use InEngine\Modulus\Facades\Modulus as ModulusFacade;
use InEngine\Modulus\Modulus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

$configFixturesPath = realpath(getcwd() . '/tests/fixtures');
$balancedConfigFileName = '/modules_balanced.php';
$emptyConfigFileName = '/modules_empty.php';
$stubConfigFile = realpath(getcwd() . '/stubs/modules.stub.php');
$configFileName = '/modules.php';
$configPath =  realpath(getcwd() . '/vendor/orchestra/testbench-core/laravel/config');



//TODO: fix pushing the version of the module.php config file
beforeEach(function () use($configPath, $configFileName, $stubConfigFile, $configFixturesPath, $balancedConfigFileName) {
    $this->copyBalancedConfigFile($configPath, $configFileName, $configFixturesPath, $balancedConfigFileName);
    $this->modulus = new Modulus();
    $this->withoutExceptionHandling();
    //$this->resetConfig($configPath, $configFileName, $stubConfigFile);
});

afterEach(function () use($configPath, $configFileName, $stubConfigFile,$configFixturesPath, $balancedConfigFileName) {
    //$this->resetConfig($configPath, $configFileName, $stubConfigFile);
    $this->copyBalancedConfigFile($configPath, $configFileName, $configFixturesPath, $balancedConfigFileName);
    //Issue: for some reason the file copy does not complete before the test is run so we have to copy the balanced config file
    // before and after running the test or it will fail every other time.
});



it('the class object deactivates a module listed as activated', function () use ($configPath, $configFileName, $configFixturesPath, $balancedConfigFileName) {

    //dd(config('modules'));
    //dd($this->modulus->activateModule('Course', false, true));
    expect($this->modulus->deactivateModule('Person', false, true))
        ->toEqual(config('modules.inactive'))
        ->not->toBeEmpty();
    //dd($this->modulus->getInActiveModules());
});
