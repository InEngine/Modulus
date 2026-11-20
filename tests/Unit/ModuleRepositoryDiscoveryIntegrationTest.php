<?php

/**
 * Integration-style coverage of {@see ModuleRepository} with
 * {@see FilteringPackageDiscoverySyncer} and
 * {@see FilteringPackageManifest}.
 */

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleDependencyGuard;
use InEngine\Modulus\Contracts\ModuleLifecycleRunner;
use InEngine\Modulus\Contracts\ModuleMigrator;
use InEngine\Modulus\Contracts\ModulePublisher;
use InEngine\Modulus\Contracts\ModuleSeederRunner;
use InEngine\Modulus\Contracts\PackageDiscoverySyncer;
use InEngine\Modulus\Discovery\FilteringPackageDiscoverySyncer;
use InEngine\Modulus\Discovery\FilteringPackageManifest;
use InEngine\Modulus\ModuleRepository;
use InEngine\Modulus\State\FileModulusStateStore;

/**
 * Build permissive Mockery doubles for {@see ModuleRepository} collaborators not under test here.
 *
 * @return array{0: ModuleMigrator, 1: ModulePublisher, 2: ModuleSeederRunner, 3: ModuleLifecycleRunner, 4: ModuleDependencyGuard, 5: Dispatcher}
 *                                                                                                                                                Migrator, publisher, seeders, lifecycle, dependency guard, event dispatcher.
 */
function moduleRepositoryLifecycleDoubles(): array
{
    $migrator = Mockery::mock(ModuleMigrator::class);
    $migrator->shouldReceive('migrate')->byDefault();

    $publisher = Mockery::mock(ModulePublisher::class);
    $publisher->shouldReceive('publish')->byDefault();

    $seeders = Mockery::mock(ModuleSeederRunner::class);
    $seeders->shouldIgnoreMissing();

    $lifecycle = Mockery::mock(ModuleLifecycleRunner::class);
    $lifecycle->shouldIgnoreMissing();

    $dependencies = Mockery::mock(ModuleDependencyGuard::class);
    $dependencies->shouldReceive('assertCanEnable')->byDefault();
    $dependencies->shouldReceive('assertCanDisable')->byDefault();

    $events = Mockery::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->byDefault();

    return [$migrator, $publisher, $seeders, $lifecycle, $dependencies, $events];
}

it('enable and disable change filtered providers after discovery sync', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-integ-'.uniqid('', true);
    $statePath = $directory.DIRECTORY_SEPARATOR.'modules.json';
    $packagesPath = $directory.DIRECTORY_SEPARATOR.'packages.php';
    mkdir($directory, 0755, true);

    file_put_contents(
        $packagesPath,
        '<?php return '.var_export([
            'inengine/alert' => [
                'providers' => ['InEngine\\Alert\\AlertServiceProvider'],
            ],
            'livewire/livewire' => [
                'providers' => ['Livewire\\LivewireServiceProvider'],
            ],
        ], true).';'
    );

    $store = new FileModulusStateStore($statePath, new Filesystem, false);
    $syncer = new FilteringPackageDiscoverySyncer(app());
    $installed = Mockery::mock(InstalledPackageLocator::class);
    $installed->shouldReceive('isInstalled')->andReturn(true);
    $installed->shouldReceive('installPath')->andReturn(null)->byDefault();
    $installed->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();
    $installed->shouldReceive('composerRequires')->andReturn([])->byDefault();
    $installed->shouldReceive('installedPackageNames')->andReturn([])->byDefault();
    $installed->shouldReceive('version')->andReturn(null)->byDefault();
    $installed->shouldReceive('rootConstraint')->andReturn(null)->byDefault();
    [$migrator, $publisher, $seeders, $lifecycle, $dependencies, $events] = moduleRepositoryLifecycleDoubles();
    $repo = new ModuleRepository($store, $syncer, $installed, $migrator, $publisher, $seeders, $lifecycle, $dependencies, $events, ['inengine/modulus']);

    $makeManifest = function () use ($directory, $packagesPath, $store): FilteringPackageManifest {
        return new FilteringPackageManifest(
            new Filesystem,
            $directory,
            $packagesPath,
            $store,
            'modules',
            ['inengine/modulus'],
        );
    };

    try {
        $repo->register('inengine/alert', '1.0.0');

        $before = $makeManifest();
        expect($before->providers())
            ->not->toContain('InEngine\\Alert\\AlertServiceProvider')
            ->toContain('Livewire\\LivewireServiceProvider');

        $repo->enable('inengine/alert');

        $enabled = $makeManifest();
        expect($enabled->providers())
            ->toContain('InEngine\\Alert\\AlertServiceProvider')
            ->toContain('Livewire\\LivewireServiceProvider');

        $repo->disable('inengine/alert');

        $disabled = $makeManifest();
        expect($disabled->providers())
            ->not->toContain('InEngine\\Alert\\AlertServiceProvider')
            ->toContain('Livewire\\LivewireServiceProvider');
    } finally {
        foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($directory);
    }
});

it('discovery syncer is invoked by repository toggles', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-integ-'.uniqid('', true);
    $statePath = $directory.DIRECTORY_SEPARATOR.'modules.json';

    $store = new FileModulusStateStore($statePath, new Filesystem, false);
    $syncer = Mockery::mock(PackageDiscoverySyncer::class);
    // enable: register(new)+sync then setEnabled+sync; disable: setEnabled+sync
    $syncer->shouldReceive('sync')->times(3);
    $installed = Mockery::mock(InstalledPackageLocator::class);
    $installed->shouldReceive('isInstalled')->andReturn(true);
    $installed->shouldReceive('installPath')->andReturn(null)->byDefault();
    $installed->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();
    $installed->shouldReceive('composerRequires')->andReturn([])->byDefault();
    $installed->shouldReceive('installedPackageNames')->andReturn([])->byDefault();
    $installed->shouldReceive('version')->andReturn(null)->byDefault();
    $installed->shouldReceive('rootConstraint')->andReturn(null)->byDefault();
    [$migrator, $publisher, $seeders, $lifecycle, $dependencies, $events] = moduleRepositoryLifecycleDoubles();

    $repo = new ModuleRepository($store, $syncer, $installed, $migrator, $publisher, $seeders, $lifecycle, $dependencies, $events, ['inengine/modulus']);

    try {
        $repo->enable('inengine/alert');
        $repo->disable('inengine/alert');
    } finally {
        if (is_file($statePath)) {
            unlink($statePath);
        }
        if (is_dir($directory)) {
            @rmdir($directory);
        }
    }
});
