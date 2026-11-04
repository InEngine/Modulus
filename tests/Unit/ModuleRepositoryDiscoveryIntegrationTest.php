<?php

use Illuminate\Filesystem\Filesystem;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\PackageDiscoverySyncer;
use InEngine\Modulus\Discovery\FilteringPackageDiscoverySyncer;
use InEngine\Modulus\Discovery\FilteringPackageManifest;
use InEngine\Modulus\ModuleRepository;
use InEngine\Modulus\State\FileModulusStateStore;

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
    $repo = new ModuleRepository($store, $syncer, $installed, ['inengine/modulus']);

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

    $repo = new ModuleRepository($store, $syncer, $installed, ['inengine/modulus']);

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
