<?php

/**
 * Unit coverage for {@see \InEngine\Modulus\Discovery\PackageManifestInstaller} rebinding the package manifest to
 * {@see \InEngine\Modulus\Discovery\FilteringPackageManifest} before provider registration.
 */

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\PackageManifest;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Discovery\FilteringPackageManifest;
use InEngine\Modulus\Discovery\PackageManifestInstaller;
use InEngine\Modulus\Exceptions\InvalidModulusStateStoreException;
use InEngine\Modulus\State\FileModulusStateStore;

/**
 * Create a temp directory with an empty `packages.php` for {@see PackageManifestInstaller} tests.
 *
 * Pair with `modulusInstallerCleanup()`.
 *
 * @return array{0: string, 1: string, 2: string} Temp directory, state file path (not yet created), packages path.
 */
function modulusInstallerFixture(): array
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-install-'.uniqid('', true);
    $statePath = $directory.DIRECTORY_SEPARATOR.'modules.json';
    $packagesPath = $directory.DIRECTORY_SEPARATOR.'packages.php';
    mkdir($directory, 0755, true);
    file_put_contents($packagesPath, '<?php return [];');

    return [$directory, $statePath, $packagesPath];
}

/**
 * Remove the temp directory and files created by `modulusInstallerFixture()`.
 *
 * @param  string  $directory  Temp directory returned by the fixture.
 */
function modulusInstallerCleanup(string $directory): void
{
    foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($directory);
}

/**
 * Install {@see PackageManifestInstaller} and resolve the rebound manifest as the framework would.
 *
 * Registers a stock {@see PackageManifest} singleton for the given paths, runs the installer,
 * dispatches the `bootstrapping: RegisterProviders` event, then resolves the manifest.
 *
 * @param  Application  $app  Application under test.
 * @param  string  $directory  Base path for the manifest.
 * @param  string  $packagesPath  Path to `packages.php`.
 */
function modulusInstallerResolveManifest(Application $app, string $directory, string $packagesPath): FilteringPackageManifest
{
    app()->forgetInstance(ModulusStateStore::class);
    app()->forgetInstance(PackageManifest::class);

    $app->singleton(PackageManifest::class, fn () => new PackageManifest(
        $app['files'],
        $directory,
        $packagesPath,
    ));

    PackageManifestInstaller::install($app);
    $app['events']->dispatch('bootstrapping: '.RegisterProviders::class, [$app]);

    /** @var FilteringPackageManifest $resolved */
    $resolved = $app->make(PackageManifest::class);

    return $resolved;
}

it('install rebinds PackageManifest to FilteringPackageManifest before RegisterProviders', function () {
    [$directory, $statePath, $packagesPath] = modulusInstallerFixture();

    try {
        /** @var Application $app */
        $app = app();

        config()->set('modulus.store', FileModulusStateStore::class);
        config()->set('modulus.state_path', $statePath);
        config()->set('modulus.state_fsync', false);
        config()->set('modulus.discovery.deny', ['inengine/modulus']);
        config()->set('modulus.discovery.scope', 'modules');

        $resolved = modulusInstallerResolveManifest($app, $directory, $packagesPath);

        expect($resolved)->toBeInstanceOf(FilteringPackageManifest::class)
            ->and($app->make(ModulusStateStore::class))->toBeInstanceOf(FileModulusStateStore::class);
    } finally {
        modulusInstallerCleanup($directory);
    }
});

it('normalizes a non-array discovery deny list to the Modulus default', function () {
    [$directory, $statePath, $packagesPath] = modulusInstallerFixture();

    try {
        /** @var Application $app */
        $app = app();

        config()->set('modulus.store', FileModulusStateStore::class);
        config()->set('modulus.state_path', $statePath);
        config()->set('modulus.discovery.deny', 'not-an-array');
        config()->set('modulus.discovery.scope', 'modules');

        $resolved = modulusInstallerResolveManifest($app, $directory, $packagesPath);
        $deny = (new ReflectionClass($resolved))->getProperty('deny');

        expect($deny->getValue($resolved))->toBe(['inengine/modulus']);
    } finally {
        modulusInstallerCleanup($directory);
    }
});

it('falls back to modules scope when discovery scope is empty', function () {
    [$directory, $statePath, $packagesPath] = modulusInstallerFixture();

    try {
        /** @var Application $app */
        $app = app();

        config()->set('modulus.store', FileModulusStateStore::class);
        config()->set('modulus.state_path', $statePath);
        config()->set('modulus.discovery.deny', ['inengine/modulus']);
        config()->set('modulus.discovery.scope', '');

        $resolved = modulusInstallerResolveManifest($app, $directory, $packagesPath);
        $scope = (new ReflectionClass($resolved))->getProperty('scope');

        expect($scope->getValue($resolved))->toBe('modules');
    } finally {
        modulusInstallerCleanup($directory);
    }
});

it('loads package config defaults when modulus.store is not set yet', function () {
    [$directory, $statePath, $packagesPath] = modulusInstallerFixture();

    try {
        /** @var Application $app */
        $app = app();

        config()->set('modulus', []);
        expect(config()->has('modulus.store'))->toBeFalse();

        config()->set('modulus.state_path', $statePath);

        $resolved = modulusInstallerResolveManifest($app, $directory, $packagesPath);

        expect($resolved)->toBeInstanceOf(FilteringPackageManifest::class)
            ->and(config('modulus.store'))->toBe(FileModulusStateStore::class)
            ->and($app->make(ModulusStateStore::class))->toBeInstanceOf(FileModulusStateStore::class);
    } finally {
        modulusInstallerCleanup($directory);
    }
});

it('keeps an already-bound state store instead of rebinding', function () {
    [$directory, $statePath, $packagesPath] = modulusInstallerFixture();

    try {
        /** @var Application $app */
        $app = app();

        config()->set('modulus.store', FileModulusStateStore::class);
        config()->set('modulus.state_path', $statePath);
        config()->set('modulus.discovery.deny', ['inengine/modulus']);
        config()->set('modulus.discovery.scope', 'modules');

        app()->forgetInstance(PackageManifest::class);

        $existing = new FileModulusStateStore($statePath, $app['files'], fsync: false);
        $app->instance(ModulusStateStore::class, $existing);

        $app->singleton(PackageManifest::class, fn () => new PackageManifest(
            $app['files'],
            $directory,
            $packagesPath,
        ));

        PackageManifestInstaller::install($app);
        $app['events']->dispatch('bootstrapping: '.RegisterProviders::class, [$app]);
        $app->make(PackageManifest::class);

        expect($app->make(ModulusStateStore::class))->toBe($existing);
    } finally {
        modulusInstallerCleanup($directory);
    }
});

it('exposes the package default config path', function () {
    expect(PackageManifestInstaller::defaultConfigPath())
        ->toEndWith('/config/modulus.php')
        ->and(is_file(PackageManifestInstaller::defaultConfigPath()))->toBeTrue();
});

it('throws when resolving the early manifest with an invalid state store', function () {
    [$directory, $statePath, $packagesPath] = modulusInstallerFixture();

    try {
        /** @var Application $app */
        $app = app();

        config()->set('modulus.store', stdClass::class);
        config()->set('modulus.state_path', $statePath);
        config()->set('modulus.discovery.deny', ['inengine/modulus']);
        config()->set('modulus.discovery.scope', 'modules');

        expect(fn () => modulusInstallerResolveManifest($app, $directory, $packagesPath))
            ->toThrow(InvalidModulusStateStoreException::class);
    } finally {
        modulusInstallerCleanup($directory);
    }
});
