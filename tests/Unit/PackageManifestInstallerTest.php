<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\PackageManifest;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Discovery\FilteringPackageManifest;
use InEngine\Modulus\Discovery\PackageManifestInstaller;
use InEngine\Modulus\State\FileModulusStateStore;

it('install rebinds PackageManifest to FilteringPackageManifest before RegisterProviders', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-install-'.uniqid('', true);
    $statePath = $directory.DIRECTORY_SEPARATOR.'modules.json';
    $packagesPath = $directory.DIRECTORY_SEPARATOR.'packages.php';
    mkdir($directory, 0755, true);

    file_put_contents($packagesPath, '<?php return [];');

    /** @var Application $app */
    $app = app();

    config()->set('modulus.store', FileModulusStateStore::class);
    config()->set('modulus.state_path', $statePath);
    config()->set('modulus.state_fsync', false);
    config()->set('modulus.discovery.deny', ['inengine/modulus']);
    config()->set('modulus.discovery.scope', 'modules');

    app()->forgetInstance(ModulusStateStore::class);
    app()->forgetInstance(PackageManifest::class);

    // Baseline Laravel-style binding (non-filtering).
    $app->singleton(PackageManifest::class, fn () => new PackageManifest(
        $app['files'],
        $directory,
        $packagesPath,
    ));

    PackageManifestInstaller::install($app);

    // Simulate the RegisterProviders bootstrap hook firing.
    $app['events']->dispatch('bootstrapping: '.RegisterProviders::class, [$app]);

    $resolved = $app->make(PackageManifest::class);

    expect($resolved)->toBeInstanceOf(FilteringPackageManifest::class)
        ->and($app->make(ModulusStateStore::class))->toBeInstanceOf(FileModulusStateStore::class);

    foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($directory);
});
