<?php

/**
 * Unit coverage for {@see \InEngine\Modulus\Composer\ComposerInstalledPackageLocator} reading installed package metadata.
 */

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
        ->and($locator->laravelProviderClasses($missing))->toBe([])
        ->and($locator->composerRequires($missing))->toBe([]);
});

it('reads non-platform composer requires for an installed package', function () {
    $locator = new ComposerInstalledPackageLocator;

    $requires = $locator->composerRequires('inengine/modulus');

    expect($requires)->toContain('illuminate/contracts')
        ->and($requires)->not->toContain('php')
        ->and($requires)->not->toContain('ext-json');
});
