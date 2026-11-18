<?php

use InEngine\Modulus\Composer\ComposerInstalledPackageLocator;
use InEngine\Modulus\ModulusServiceProvider;

it('reports modulus itself as installed with an install path', function () {
    $locator = new ComposerInstalledPackageLocator;

    expect($locator->isInstalled('inengine/modulus'))->toBeTrue();

    $path = $locator->installPath('inengine/modulus');

    expect($path)->toBeString()
        ->and($path)->not->toBeEmpty()
        ->and(is_dir($path))->toBeTrue()
        ->and(is_file($path.DIRECTORY_SEPARATOR.'composer.json'))->toBeTrue();
});

it('reads laravel provider classes from composer.json', function () {
    $locator = new ComposerInstalledPackageLocator;

    $providers = $locator->laravelProviderClasses('inengine/modulus');

    expect($providers)->toContain(ModulusServiceProvider::class);
});

it('returns null path and empty providers for unknown packages', function () {
    $locator = new ComposerInstalledPackageLocator;
    $missing = 'inengine/definitely-not-installed-'.uniqid();

    expect($locator->isInstalled($missing))->toBeFalse()
        ->and($locator->installPath($missing))->toBeNull()
        ->and($locator->laravelProviderClasses($missing))->toBe([]);
});
