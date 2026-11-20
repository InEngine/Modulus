<?php

/**
 * Unit coverage for {@see ComposerModuleDependencyGuard} enable/disable dependency checks.
 */

use Illuminate\Filesystem\Filesystem;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Dependencies\ComposerModuleDependencyGuard;
use InEngine\Modulus\Exceptions\ModuleHasDependentsException;
use InEngine\Modulus\Exceptions\UnmetModuleDependencyException;
use InEngine\Modulus\State\FileModulusStateStore;
use Mockery\MockInterface;

/**
 * Build a {@see ComposerModuleDependencyGuard} over a temp {@see FileModulusStateStore} and a mocked locator.
 *
 * The locator defaults to "installed" with no requires, providers, or version info; the guard
 * denies `inengine/modulus` from being managed. Pair with `dependencyGuardCleanup()`.
 *
 * @return array{0: string, 1: string, 2: FileModulusStateStore, 3: ComposerModuleDependencyGuard, 4: InstalledPackageLocator&MockInterface}
 *                                                                                                                                           Temp directory, state file path, store, guard, locator mock.
 */
function dependencyGuardFixture(): array
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-deps-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';
    $store = new FileModulusStateStore($path, new Filesystem, false);

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('isInstalled')->andReturn(true)->byDefault();
    $locator->shouldReceive('composerRequires')->andReturn([])->byDefault();
    $locator->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();
    $locator->shouldReceive('installedPackageNames')->andReturn([])->byDefault();
    $locator->shouldReceive('version')->andReturn(null)->byDefault();
    $locator->shouldReceive('rootConstraint')->andReturn(null)->byDefault();

    $guard = new ComposerModuleDependencyGuard($store, $locator, ['inengine/modulus']);

    return [$directory, $path, $store, $guard, $locator];
}

/**
 * Remove the temp state file and directory created by `dependencyGuardFixture()`.
 *
 * @param  string  $directory  Temp directory returned by the fixture.
 * @param  string  $path  State file path returned by the fixture.
 */
function dependencyGuardCleanup(string $directory, string $path): void
{
    if (is_file($path)) {
        unlink($path);
    }

    if (is_dir($directory)) {
        foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($directory);
    }
}

it('blocks enable when a required Modulus module is disabled', function () {
    [$directory, $path, $store, $guard, $locator] = dependencyGuardFixture();

    $store->put('inengine/users', ['enabled' => false, 'version' => '1.0.0']);
    $locator->shouldReceive('composerRequires')
        ->with('inengine/alert')
        ->andReturn(['inengine/users', 'guzzlehttp/guzzle']);
    $locator->shouldReceive('isInstalled')->with('inengine/users')->andReturn(true);
    $locator->shouldReceive('isInstalled')->with('guzzlehttp/guzzle')->andReturn(true);

    try {
        expect(fn () => $guard->assertCanEnable('inengine/alert'))
            ->toThrow(UnmetModuleDependencyException::class, 'disabled Modulus modules [inengine/users]');
    } finally {
        dependencyGuardCleanup($directory, $path);
    }
});

it('blocks enable when a required non-Modulus package is missing', function () {
    [$directory, $path, , $guard, $locator] = dependencyGuardFixture();

    $locator->shouldReceive('composerRequires')
        ->with('inengine/alert')
        ->andReturn(['guzzlehttp/guzzle']);
    $locator->shouldReceive('isInstalled')->with('guzzlehttp/guzzle')->andReturn(false);

    try {
        expect(fn () => $guard->assertCanEnable('inengine/alert'))
            ->toThrow(UnmetModuleDependencyException::class, 'missing Composer packages [guzzlehttp/guzzle]');
    } finally {
        dependencyGuardCleanup($directory, $path);
    }
});

it('allows enable when Modulus deps are enabled and non-Modulus deps are installed', function () {
    [$directory, $path, $store, $guard, $locator] = dependencyGuardFixture();

    $store->put('inengine/users', ['enabled' => true, 'version' => '1.0.0']);
    $locator->shouldReceive('composerRequires')
        ->with('inengine/alert')
        ->andReturn(['inengine/users', 'guzzlehttp/guzzle', 'inengine/modulus']);
    $locator->shouldReceive('isInstalled')->andReturn(true);

    try {
        $guard->assertCanEnable('inengine/alert');
        expect(true)->toBeTrue();
    } finally {
        dependencyGuardCleanup($directory, $path);
    }
});

it('treats deny-list packages as always-enabled Modulus modules', function () {
    [$directory, $path, , $guard, $locator] = dependencyGuardFixture();

    $locator->shouldReceive('composerRequires')
        ->with('inengine/alert')
        ->andReturn(['inengine/modulus']);

    try {
        expect($guard->isModulusModule('inengine/modulus'))->toBeTrue();
        $guard->assertCanEnable('inengine/alert');
    } finally {
        dependencyGuardCleanup($directory, $path);
    }
});

it('blocks disable when an enabled module requires the package', function () {
    [$directory, $path, $store, $guard, $locator] = dependencyGuardFixture();

    $store->put('inengine/users', ['enabled' => true, 'version' => '1.0.0']);
    $store->put('inengine/alert', ['enabled' => true, 'version' => '1.0.0']);
    $locator->shouldReceive('composerRequires')
        ->with('inengine/alert')
        ->andReturn(['inengine/users']);
    $locator->shouldReceive('composerRequires')
        ->with('inengine/users')
        ->andReturn([])
        ->byDefault();

    try {
        expect(fn () => $guard->assertCanDisable('inengine/users'))
            ->toThrow(ModuleHasDependentsException::class, 'inengine/alert');
    } finally {
        dependencyGuardCleanup($directory, $path);
    }
});

it('allows disable when only disabled modules require the package', function () {
    [$directory, $path, $store, $guard, $locator] = dependencyGuardFixture();

    $store->put('inengine/users', ['enabled' => true, 'version' => '1.0.0']);
    $store->put('inengine/alert', ['enabled' => false, 'version' => '1.0.0']);
    $locator->shouldReceive('composerRequires')
        ->with('inengine/alert')
        ->andReturn(['inengine/users']);

    try {
        $guard->assertCanDisable('inengine/users');
        expect(true)->toBeTrue();
    } finally {
        dependencyGuardCleanup($directory, $path);
    }
});
