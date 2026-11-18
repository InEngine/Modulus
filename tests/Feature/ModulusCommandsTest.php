<?php

use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleDependencyGuard;
use InEngine\Modulus\Contracts\ModuleLifecycleRunner;
use InEngine\Modulus\Contracts\ModuleMigrator;
use InEngine\Modulus\Contracts\ModulePublisher;
use InEngine\Modulus\Contracts\ModuleSeeder;
use InEngine\Modulus\Contracts\ModuleSeederRunner;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Contracts\PackageDiscoverySyncer;
use InEngine\Modulus\Diagnostics\ModulusDoctor;
use InEngine\Modulus\ModuleRepository;
use InEngine\Modulus\State\FileModulusStateStore;

function rebindModulusForCommands(?callable $configureLocator = null): void
{
    $path = config('modulus.state_path');
    $store = new FileModulusStateStore($path, app('files'), false);
    app()->instance(ModulusStateStore::class, $store);

    $syncer = Mockery::mock(PackageDiscoverySyncer::class);
    $syncer->shouldReceive('sync')->byDefault();
    app()->instance(PackageDiscoverySyncer::class, $syncer);

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('isInstalled')->andReturn(true)->byDefault();
    $locator->shouldReceive('installPath')->andReturn(null)->byDefault();
    $locator->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();
    $locator->shouldReceive('composerRequires')->andReturn([])->byDefault();
    $locator->shouldReceive('installedPackageNames')->andReturn([])->byDefault();
    $locator->shouldReceive('version')->andReturn('1.0.0')->byDefault();
    $locator->shouldReceive('rootConstraint')->andReturn('~1.0.0')->byDefault();
    if ($configureLocator) {
        $configureLocator($locator);
    }
    app()->instance(InstalledPackageLocator::class, $locator);

    $migrator = Mockery::mock(ModuleMigrator::class);
    $migrator->shouldReceive('migrate')->byDefault();
    app()->instance(ModuleMigrator::class, $migrator);

    $publisher = Mockery::mock(ModulePublisher::class);
    $publisher->shouldReceive('publish')->byDefault();
    app()->instance(ModulePublisher::class, $publisher);

    $seeders = Mockery::mock(ModuleSeederRunner::class);
    $seeders->shouldIgnoreMissing();
    app()->instance(ModuleSeederRunner::class, $seeders);

    $lifecycle = Mockery::mock(ModuleLifecycleRunner::class);
    $lifecycle->shouldIgnoreMissing();
    app()->instance(ModuleLifecycleRunner::class, $lifecycle);

    $dependencies = Mockery::mock(ModuleDependencyGuard::class);
    $dependencies->shouldReceive('assertCanEnable')->byDefault();
    $dependencies->shouldReceive('assertCanDisable')->byDefault();
    app()->instance(ModuleDependencyGuard::class, $dependencies);

    app()->forgetInstance(ModuleRepository::class);
    app()->forgetInstance(ModulusDoctor::class);
}

it('lists modules via modulus:list', function () {
    rebindModulusForCommands();
    app(ModuleRepository::class)->register('inengine/alert', '1.0.0');

    $this->artisan('modulus:list')
        ->expectsOutputToContain('inengine/alert')
        ->assertSuccessful();
});

it('enables and disables through artisan', function () {
    rebindModulusForCommands();

    $this->artisan('modulus:enable', ['package' => 'inengine/alert'])->assertSuccessful();
    expect(app(ModuleRepository::class)->isEnabled('inengine/alert'))->toBeTrue();

    $this->artisan('modulus:disable', ['package' => 'inengine/alert'])->assertSuccessful();
    expect(app(ModuleRepository::class)->isEnabled('inengine/alert'))->toBeFalse();
});

it('publishes and upgrades through artisan', function () {
    rebindModulusForCommands();
    app(ModuleRepository::class)->register('inengine/alert', '1.0.0');
    app(ModuleRepository::class)->enable('inengine/alert');

    $locator = app(InstalledPackageLocator::class);
    $locator->shouldReceive('version')->with('inengine/alert')->andReturn('1.0.1');

    $this->artisan('modulus:publish', ['package' => 'inengine/alert'])->assertSuccessful();
    $this->artisan('modulus:upgrade', ['package' => 'inengine/alert', '--publish' => true])->assertSuccessful();

    expect(app(ModuleRepository::class)->get('inengine/alert')['version'])->toBe('1.0.1');
});

it('seeds with --force', function () {
    rebindModulusForCommands();

    $seeder = Mockery::mock(ModuleSeeder::class);
    $seeder->shouldReceive('requiresConfirmation')->andReturn(true);
    $seeder->shouldReceive('run')->once();
    app(ModuleSeederRunner::class)->shouldReceive('resolve')->andReturn($seeder);

    $this->artisan('modulus:seed', ['package' => 'inengine/alert', '--force' => true])
        ->assertSuccessful();
});

it('doctor exits non-zero when an enabled module is missing', function () {
    rebindModulusForCommands(function ($locator) {
        $locator->shouldReceive('isInstalled')->with('inengine/alert')->andReturn(false);
    });

    app(ModuleRepository::class)->register('inengine/alert', '1.0.0');
    $store = app(ModulusStateStore::class);
    $store->setEnabled('inengine/alert', true);

    // Avoid boot-time validate throwing; doctor should still see enabled+missing.
    app()->forgetInstance(ModuleRepository::class);

    $this->artisan('modulus:doctor')->assertFailed();
});
