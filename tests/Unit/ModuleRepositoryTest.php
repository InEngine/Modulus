<?php

/**
 * Unit coverage for {@see ModuleRepository} state transitions, lifecycle hooks, guards, and events.
 */

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use InEngine\Modulus\Capabilities\ModuleCapabilityLocator;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleDependencyGuard;
use InEngine\Modulus\Contracts\ModuleLifecycleRunner;
use InEngine\Modulus\Contracts\ModuleMigrator;
use InEngine\Modulus\Contracts\ModulePublisher;
use InEngine\Modulus\Contracts\ModuleSeeder;
use InEngine\Modulus\Contracts\ModuleSeederRunner;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Contracts\PackageDiscoverySyncer;
use InEngine\Modulus\Events\ModuleDisabled;
use InEngine\Modulus\Events\ModuleEnabled;
use InEngine\Modulus\Events\ModuleForgotten;
use InEngine\Modulus\Events\ModulePublished;
use InEngine\Modulus\Events\ModuleRegistered;
use InEngine\Modulus\Events\ModuleSeeded;
use InEngine\Modulus\Events\ModuleUpgraded;
use InEngine\Modulus\Exceptions\MissingModuleException;
use InEngine\Modulus\Exceptions\ModuleHasDependentsException;
use InEngine\Modulus\Exceptions\SeedingConfirmationRequiredException;
use InEngine\Modulus\Exceptions\UnmetModuleDependencyException;
use InEngine\Modulus\Facades\Modulus as ModulusFacade;
use InEngine\Modulus\ModuleRepository;
use InEngine\Modulus\State\FileModulusStateStore;
use Mockery\MockInterface;

/**
 * Build a {@see ModuleRepository} over a temp {@see FileModulusStateStore} with Mockery doubles.
 *
 * Any collaborator not passed in is replaced with a permissive mock. Pair with
 * `moduleRepositoryCleanup()`.
 *
 * @param  list<string>  $deny  Package names Modulus must not manage.
 * @param  InstalledPackageLocator|null  $installedPackages  Custom locator, or a default "installed" mock.
 * @param  ModuleDependencyGuard|null  $dependencies  Custom guard, or a no-op mock.
 * @param  Dispatcher|null  $events  Custom dispatcher, or a mock that accepts `dispatch()`.
 * @param  ModuleCapabilityLocator|null  $capabilityLocator  Custom locator, or a default mock.
 * @return array{
 *     0: string,
 *     1: string,
 *     2: ModuleRepository,
 *     3: MockInterface&PackageDiscoverySyncer,
 *     4: MockInterface&InstalledPackageLocator,
 *     5: MockInterface&ModuleMigrator,
 *     6: MockInterface&ModulePublisher,
 *     7: MockInterface&ModuleSeederRunner,
 *     8: MockInterface&ModuleLifecycleRunner,
 *     9: MockInterface&ModuleDependencyGuard,
 *     10: MockInterface&Dispatcher,
 *     11: MockInterface&ModuleCapabilityLocator
 * } Temp directory, state path, repository, then each collaborator.
 */
function moduleRepositoryFixture(
    array $deny = ['inengine/modulus'],
    ?InstalledPackageLocator $installedPackages = null,
    ?ModuleDependencyGuard $dependencies = null,
    ?Dispatcher $events = null,
    ?ModuleCapabilityLocator $capabilityLocator = null,
): array {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-repo-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';
    $store = new FileModulusStateStore($path, new Filesystem, false);
    $syncer = Mockery::mock(PackageDiscoverySyncer::class);
    $syncer->shouldReceive('sync')->byDefault();

    if ($installedPackages === null) {
        $installedPackages = Mockery::mock(InstalledPackageLocator::class);
        $installedPackages->shouldReceive('isInstalled')->andReturn(true)->byDefault();
        $installedPackages->shouldReceive('installPath')->andReturn(null)->byDefault();
        $installedPackages->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();
        $installedPackages->shouldReceive('composerRequires')->andReturn([])->byDefault();
        $installedPackages->shouldReceive('installedPackageNames')->andReturn([])->byDefault();
        $installedPackages->shouldReceive('version')->andReturn(null)->byDefault();
        $installedPackages->shouldReceive('rootConstraint')->andReturn(null)->byDefault();
    }

    $migrator = Mockery::mock(ModuleMigrator::class);
    $migrator->shouldReceive('migrate')->byDefault();

    $publisher = Mockery::mock(ModulePublisher::class);
    $publisher->shouldReceive('publish')->byDefault();

    $seeders = Mockery::mock(ModuleSeederRunner::class);
    $seeders->shouldIgnoreMissing();

    $lifecycle = Mockery::mock(ModuleLifecycleRunner::class);
    $lifecycle->shouldIgnoreMissing();

    if ($dependencies === null) {
        $dependencies = Mockery::mock(ModuleDependencyGuard::class);
        $dependencies->shouldReceive('assertCanEnable')->byDefault();
        $dependencies->shouldReceive('assertCanDisable')->byDefault();
    }

    if ($events === null) {
        $events = Mockery::mock(Dispatcher::class);
        $events->shouldReceive('dispatch')->byDefault();
    }

    if ($capabilityLocator === null) {
        $capabilityLocator = Mockery::mock(ModuleCapabilityLocator::class);
        $capabilityLocator->shouldReceive('locate')->andReturn(null)->byDefault();
    }

    return [
        $directory,
        $path,
        new ModuleRepository($store, $syncer, $installedPackages, $migrator, $publisher, $seeders, $lifecycle, $dependencies, $events, $capabilityLocator, $deny),
        $syncer,
        $installedPackages,
        $migrator,
        $publisher,
        $seeders,
        $lifecycle,
        $dependencies,
        $events,
        $capabilityLocator,
    ];
}

/**
 * Remove the temp state file and directory created by `moduleRepositoryFixture()`.
 *
 * @param  string  $directory  Temp directory returned by the fixture.
 * @param  string  $path  State file path returned by the fixture.
 */
function moduleRepositoryCleanup(string $directory, string $path): void
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

it('registers new packages as disabled by default', function () {
    [$directory, $path, $repo, $syncer] = moduleRepositoryFixture();
    $syncer->shouldReceive('sync')->once();

    try {
        expect($repo->all())->toBe([]);

        $repo->register('inengine/alert', '1.0.0');

        expect($repo->get('inengine/alert'))->toEqual([
            'enabled' => false,
            'version' => '1.0.0',
        ])
            ->and($repo->isEnabled('inengine/alert'))->toBeFalse()
            ->and($repo->disabled())->toHaveKey('inengine/alert')
            ->and($repo->enabled())->toBe([]);
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('enable and disable transition state and sync discovery', function () {
    [$directory, $path, $repo, $syncer, , $migrator, $publisher, $seeders, $lifecycle] = moduleRepositoryFixture();
    $syncer->shouldReceive('sync')->times(3);

    $lifecycle->shouldReceive('activating')->once()->with('inengine/alert');
    $lifecycle->shouldReceive('activated')->once()->with('inengine/alert');
    $lifecycle->shouldReceive('deactivating')->once()->with('inengine/alert');
    $lifecycle->shouldReceive('deactivated')->once()->with('inengine/alert');
    $publisher->shouldReceive('publish')->never();
    $migrator->shouldReceive('migrate')->once()->with('inengine/alert');

    try {
        $repo->register('inengine/alert');
        expect($repo->isEnabled('inengine/alert'))->toBeFalse();

        $repo->enable('inengine/alert');
        expect($repo->isEnabled('inengine/alert'))->toBeTrue()
            ->and($repo->enabled())->toHaveKey('inengine/alert');

        $repo->disable('inengine/alert');
        expect($repo->isEnabled('inengine/alert'))->toBeFalse()
            ->and($repo->disabled())->toHaveKey('inengine/alert');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('refuses to disable packages on the deny list', function () {
    [$directory, $path, $repo] = moduleRepositoryFixture();

    try {
        expect(fn () => $repo->disable('inengine/modulus'))
            ->toThrow(InvalidArgumentException::class);

        expect($repo->isEnabled('inengine/modulus'))->toBeTrue();
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('refuses to disable library modules', function () {
    [$directory, $path, $repo] = moduleRepositoryFixture();

    try {
        $repo->register('inengine/identity-contracts', '1.0.0', [
            'kind' => 'library',
            'provides' => ['identity.contracts'],
        ]);

        expect($repo->isLibraryPackage('inengine/identity-contracts'))->toBeTrue()
            ->and(fn () => $repo->disable('inengine/identity-contracts'))
            ->toThrow(InvalidArgumentException::class, 'library module')
            ->and($repo->isEnabled('inengine/identity-contracts'))->toBeTrue();
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('refuses to forget library modules', function () {
    [$directory, $path, $repo] = moduleRepositoryFixture();

    try {
        $repo->register('inengine/identity-contracts', '1.0.0', [
            'kind' => 'library',
            'provides' => ['identity.contracts'],
        ]);

        expect(fn () => $repo->forget('inengine/identity-contracts'))
            ->toThrow(InvalidArgumentException::class, 'library module')
            ->and($repo->get('inengine/identity-contracts'))->not->toBeNull();
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('registers denied packages as enabled', function () {
    [$directory, $path, $repo, $syncer] = moduleRepositoryFixture();
    $syncer->shouldReceive('sync')->once();

    try {
        $repo->register('inengine/modulus', '1.0.0');

        expect($repo->isEnabled('inengine/modulus'))->toBeTrue()
            ->and($repo->get('inengine/modulus')['enabled'])->toBeTrue();
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('registers library modules as enabled by default', function () {
    [$directory, $path, $repo] = moduleRepositoryFixture();

    try {
        $repo->register('inengine/identity-contracts', '1.0.0', [
            'kind' => 'library',
            'provides' => ['identity.contracts'],
        ]);

        expect($repo->isEnabled('inengine/identity-contracts'))->toBeTrue()
            ->and($repo->get('inengine/identity-contracts')['capabilities']['kind'] ?? null)->toBe('library');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('heals disabled library modules to enabled on re-register', function () {
    [$directory, $path, $repo] = moduleRepositoryFixture();

    try {
        $repo->register('inengine/identity-contracts', '1.0.0');
        expect($repo->isEnabled('inengine/identity-contracts'))->toBeFalse();

        $repo->register('inengine/identity-contracts', '1.0.0', [
            'kind' => 'library',
            'provides' => ['identity.contracts'],
        ]);

        expect($repo->isEnabled('inengine/identity-contracts'))->toBeTrue()
            ->and($repo->get('inengine/identity-contracts')['capabilities']['kind'] ?? null)->toBe('library');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('promotes Composer-required library deps before enabling a feature module', function () {
    $installedPackages = Mockery::mock(InstalledPackageLocator::class);
    $installedPackages->shouldReceive('isInstalled')->andReturn(true)->byDefault();
    $installedPackages->shouldReceive('installPath')->andReturn(null)->byDefault();
    $installedPackages->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();
    $installedPackages->shouldReceive('installedPackageNames')->andReturn([])->byDefault();
    $installedPackages->shouldReceive('version')->andReturn('1.0.0')->byDefault();
    $installedPackages->shouldReceive('rootConstraint')->andReturn(null)->byDefault();
    $installedPackages->shouldReceive('composerRequires')
        ->with('inengine/user')
        ->andReturn(['inengine/identity-contracts']);

    $capabilityLocator = Mockery::mock(ModuleCapabilityLocator::class);
    $capabilityLocator->shouldReceive('locate')
        ->with('inengine/identity-contracts')
        ->andReturn([
            'kind' => 'library',
            'provides' => ['identity.contracts'],
        ]);
    $capabilityLocator->shouldReceive('locate')->andReturn(null)->byDefault();

    [$directory, $path, $repo, $syncer, , $migrator, , , $lifecycle] = moduleRepositoryFixture(
        installedPackages: $installedPackages,
        capabilityLocator: $capabilityLocator,
    );

    $lifecycle->shouldReceive('activating')->once()->with('inengine/user');
    $lifecycle->shouldReceive('activated')->once()->with('inengine/user');
    $migrator->shouldReceive('migrate')->once()->with('inengine/user');
    $syncer->shouldReceive('sync')->atLeast()->once();

    try {
        $repo->register('inengine/identity-contracts', '1.0.0');
        expect($repo->isEnabled('inengine/identity-contracts'))->toBeFalse();

        $repo->register('inengine/user', '1.0.0');
        $repo->enable('inengine/user');

        expect($repo->isEnabled('inengine/user'))->toBeTrue()
            ->and($repo->isEnabled('inengine/identity-contracts'))->toBeTrue()
            ->and($repo->get('inengine/identity-contracts')['capabilities']['kind'] ?? null)->toBe('library');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('forget removes a package and syncs discovery', function () {
    [$directory, $path, $repo, $syncer, , , , , $lifecycle] = moduleRepositoryFixture();
    $syncer->shouldReceive('sync')->twice();

    $lifecycle->shouldReceive('uninstalling')->once()->with('inengine/alert');
    $lifecycle->shouldReceive('uninstalled')->once()->with('inengine/alert');

    try {
        $repo->register('inengine/alert');
        $repo->forget('inengine/alert');

        expect($repo->all())->toBe([])
            ->and($repo->get('inengine/alert'))->toBeNull();
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('uninstall aliases forget', function () {
    [$directory, $path, $repo, $syncer, , , , , $lifecycle] = moduleRepositoryFixture();
    $syncer->shouldReceive('sync')->twice();

    $lifecycle->shouldReceive('uninstalling')->once()->with('inengine/alert');
    $lifecycle->shouldReceive('uninstalled')->once()->with('inengine/alert');

    try {
        $repo->register('inengine/alert');
        $repo->uninstall('inengine/alert');

        expect($repo->get('inengine/alert'))->toBeNull();
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('publish delegates to the module publisher for install/update', function () {
    [$directory, $path, $repo, , , , $publisher] = moduleRepositoryFixture();

    $publisher->shouldReceive('publish')->once()->with('inengine/alert');

    try {
        $repo->publish('inengine/alert');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('publish throws when the package is not installed', function () {
    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('isInstalled')->with('inengine/alert')->andReturn(false);
    $locator->shouldReceive('installPath')->andReturn(null)->byDefault();
    $locator->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();

    [$directory, $path, $repo, , , , $publisher] = moduleRepositoryFixture(installedPackages: $locator);
    $publisher->shouldReceive('publish')->never();

    try {
        expect(fn () => $repo->publish('inengine/alert'))
            ->toThrow(MissingModuleException::class);
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('seed requires confirmation when the module seeder demands it', function () {
    [$directory, $path, $repo, , , , , $seeders] = moduleRepositoryFixture();

    $seeder = Mockery::mock(ModuleSeeder::class);
    $seeder->shouldReceive('requiresConfirmation')->andReturn(true);
    $seeder->shouldReceive('run')->never();
    $seeders->shouldReceive('resolve')->once()->with('inengine/alert')->andReturn($seeder);

    try {
        expect(fn () => $repo->seed('inengine/alert'))
            ->toThrow(SeedingConfirmationRequiredException::class);
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('seed runs after explicit confirmation', function () {
    [$directory, $path, $repo, , , , , $seeders] = moduleRepositoryFixture();

    $seeder = Mockery::mock(ModuleSeeder::class);
    $seeder->shouldReceive('requiresConfirmation')->andReturn(true);
    $seeder->shouldReceive('run')->once();
    $seeders->shouldReceive('resolve')->once()->with('inengine/alert')->andReturn($seeder);

    try {
        $repo->seed('inengine/alert', confirmed: true);
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('enable does not invoke the seeder runner', function () {
    [$directory, $path, $repo, $syncer, , $migrator, $publisher, $seeders, $lifecycle] = moduleRepositoryFixture();
    $syncer->shouldReceive('sync')->times(2);

    $lifecycle->shouldReceive('activating')->once();
    $lifecycle->shouldReceive('activated')->once();
    $migrator->shouldReceive('migrate')->once();
    $publisher->shouldReceive('publish')->never();
    $seeders->shouldReceive('resolve')->never();

    try {
        $repo->enable('inengine/alert');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('forget disables an enabled package before uninstall hooks', function () {
    [$directory, $path, $repo, $syncer, , $migrator, $publisher, $seeders, $lifecycle] = moduleRepositoryFixture();
    // enable: register sync + enable sync; disable sync; forget sync
    $syncer->shouldReceive('sync')->times(4);

    $lifecycle->shouldReceive('activating')->once();
    $lifecycle->shouldReceive('activated')->once();
    $lifecycle->shouldReceive('deactivating')->once();
    $lifecycle->shouldReceive('deactivated')->once();
    $lifecycle->shouldReceive('uninstalling')->once();
    $lifecycle->shouldReceive('uninstalled')->once();
    $publisher->shouldReceive('publish')->never();
    $migrator->shouldReceive('migrate')->once();

    try {
        $repo->enable('inengine/alert');
        $repo->forget('inengine/alert');

        expect($repo->all())->toBe([]);
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('refuses to forget packages on the deny list', function () {
    [$directory, $path, $repo] = moduleRepositoryFixture();

    try {
        expect(fn () => $repo->forget('inengine/modulus'))
            ->toThrow(InvalidArgumentException::class);
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('updates version on re-register without changing enabled flag', function () {
    [$directory, $path, $repo, $syncer, , $migrator, $publisher, $seeders, $lifecycle] = moduleRepositoryFixture();
    // register (new) + enable (sync) + register (version bump) = 3
    $syncer->shouldReceive('sync')->times(3);

    $lifecycle->shouldReceive('activating')->once();
    $lifecycle->shouldReceive('activated')->once();
    $lifecycle->shouldReceive('upgrading')->once()->with('inengine/alert');
    $lifecycle->shouldReceive('upgraded')->once()->with('inengine/alert');
    $publisher->shouldReceive('publish')->never();
    // enable migrate + upgrade migrate
    $migrator->shouldReceive('migrate')->twice()->with('inengine/alert');

    try {
        $repo->register('inengine/alert', '1.0.0');
        $repo->enable('inengine/alert');

        expect($repo->get('inengine/alert'))->toEqual([
            'enabled' => true,
            'version' => '1.0.0',
        ]);

        // enable() already registered; version-only update:
        $repo->register('inengine/alert', '1.1.0');

        expect($repo->get('inengine/alert'))->toEqual([
            'enabled' => true,
            'version' => '1.1.0',
        ])
            ->and($repo->isEnabled('inengine/alert'))->toBeTrue();
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('does not migrate on version bump when the package is disabled', function () {
    [$directory, $path, $repo, $syncer, , $migrator, $publisher, $seeders, $lifecycle] = moduleRepositoryFixture();
    $syncer->shouldReceive('sync')->twice();

    $publisher->shouldReceive('publish')->never();
    $migrator->shouldReceive('migrate')->never();
    $lifecycle->shouldReceive('upgrading')->never();
    $lifecycle->shouldReceive('upgraded')->never();

    try {
        $repo->register('inengine/alert', '1.0.0');
        $repo->register('inengine/alert', '1.1.0');

        expect($repo->get('inengine/alert')['version'])->toBe('1.1.0')
            ->and($repo->isEnabled('inengine/alert'))->toBeFalse();
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('re-enable only migrates without re-firing activate hooks', function () {
    [$directory, $path, $repo, $syncer, , $migrator, $publisher, $seeders, $lifecycle] = moduleRepositoryFixture();
    $syncer->shouldReceive('sync')->times(2);

    $lifecycle->shouldReceive('activating')->once();
    $lifecycle->shouldReceive('activated')->once();
    $publisher->shouldReceive('publish')->never();
    $migrator->shouldReceive('migrate')->twice()->with('inengine/alert');

    try {
        $repo->enable('inengine/alert');
        $repo->enable('inengine/alert');

        expect($repo->isEnabled('inengine/alert'))->toBeTrue();
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('does not publish or migrate on disable or forget', function () {
    [$directory, $path, $repo, $syncer, , $migrator, $publisher, $seeders, $lifecycle] = moduleRepositoryFixture();
    $syncer->shouldReceive('sync')->times(4);

    $lifecycle->shouldReceive('activating')->once();
    $lifecycle->shouldReceive('activated')->once();
    $lifecycle->shouldReceive('deactivating')->once();
    $lifecycle->shouldReceive('deactivated')->once();
    $lifecycle->shouldReceive('uninstalling')->once();
    $lifecycle->shouldReceive('uninstalled')->once();
    $publisher->shouldReceive('publish')->never();
    $migrator->shouldReceive('migrate')->once()->with('inengine/alert');

    try {
        $repo->enable('inengine/alert');
        $repo->disable('inengine/alert');
        $repo->forget('inengine/alert');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('skips activate hooks when migrate throws during enable', function () {
    [$directory, $path, $repo, $syncer, , $migrator, $publisher, $seeders, $lifecycle] = moduleRepositoryFixture();
    $syncer->shouldReceive('sync')->never();

    $lifecycle->shouldReceive('activating')->once()->with('inengine/alert');
    $lifecycle->shouldReceive('activated')->never();
    $publisher->shouldReceive('publish')->never();
    $migrator->shouldReceive('migrate')->once()->andThrow(new RuntimeException('migrate failed'));

    try {
        expect(fn () => $repo->enable('inengine/alert'))
            ->toThrow(RuntimeException::class, 'migrate failed');

        expect($repo->isEnabled('inengine/alert'))->toBeFalse()
            ->and($repo->get('inengine/alert'))->toBeNull();
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('resolves the repository from the container and facade', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-repo-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';

    try {
        config()->set('modulus.store', FileModulusStateStore::class);
        config()->set('modulus.state_path', $path);
        config()->set('modulus.state_fsync', false);
        config()->set('modulus.discovery.deny', ['inengine/modulus']);

        $locator = Mockery::mock(InstalledPackageLocator::class);
        $locator->shouldReceive('isInstalled')->andReturn(true);
        $locator->shouldReceive('installPath')->andReturn(null)->byDefault();
        $locator->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();
        $locator->shouldReceive('composerRequires')->andReturn([])->byDefault();
        $locator->shouldReceive('version')->andReturn(null)->byDefault();
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

        app()->forgetInstance(ModulusStateStore::class);
        app()->forgetInstance(ModuleRepository::class);
        app()->forgetInstance(ModuleMigrator::class);
        app()->forgetInstance(ModulePublisher::class);
        app()->forgetInstance(ModuleSeederRunner::class);
        app()->forgetInstance(ModuleLifecycleRunner::class);

        $repo = app(ModuleRepository::class);

        expect($repo)->toBeInstanceOf(ModuleRepository::class);

        ModulusFacade::register('inengine/alert', '0.1.0');
        ModulusFacade::enable('inengine/alert');

        expect(ModulusFacade::isEnabled('inengine/alert'))->toBeTrue()
            ->and(ModulusFacade::enabled())->toHaveKey('inengine/alert');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('throws when enabling a package that is not installed', function () {
    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('isInstalled')->with('inengine/alert')->andReturn(false);
    $locator->shouldReceive('installPath')->andReturn(null)->byDefault();
    $locator->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();

    [$directory, $path, $repo] = moduleRepositoryFixture(installedPackages: $locator);

    try {
        expect(fn () => $repo->enable('inengine/alert'))
            ->toThrow(MissingModuleException::class, 'enabled module [inengine/alert]');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('throws when an enabled tracked package is not installed', function () {
    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('isInstalled')->with('inengine/alert')->andReturn(false);
    $locator->shouldReceive('installPath')->andReturn(null)->byDefault();
    $locator->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();

    [$directory, $path, $repo] = moduleRepositoryFixture(installedPackages: $locator);

    try {
        $repo->register('inengine/alert');
        // Bypass enable() install check by writing state directly.
        $repo->get('inengine/alert');
        $store = new ReflectionProperty($repo, 'store');
        $store->getValue($repo)->setEnabled('inengine/alert', true);

        expect(fn () => $repo->validateTrackedPackages())
            ->toThrow(MissingModuleException::class, 'enabled module [inengine/alert]');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('logs when a disabled tracked package is not installed', function () {
    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('isInstalled')->with('inengine/alert')->andReturn(false);
    $locator->shouldReceive('installPath')->andReturn(null)->byDefault();
    $locator->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();

    [$directory, $path, $repo] = moduleRepositoryFixture(installedPackages: $locator);

    try {
        $repo->register('inengine/alert');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'disabled module [inengine/alert]')
                    && ($context['package'] ?? null) === 'inengine/alert'
                    && ($context['enabled'] ?? null) === false
                    && ($context['exception'] ?? null) instanceof MissingModuleException;
            });

        $repo->validateTrackedPackages();

        expect($repo->isEnabled('inengine/alert'))->toBeFalse();
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('refuses to enable when dependency guard reports unmet requires', function () {
    $dependencies = Mockery::mock(ModuleDependencyGuard::class);
    $dependencies->shouldReceive('assertCanEnable')
        ->once()
        ->with('inengine/alert')
        ->andThrow(new UnmetModuleDependencyException(
            'inengine/alert',
            missingPackages: ['guzzlehttp/guzzle'],
            disabledModules: ['inengine/users'],
        ));
    $dependencies->shouldReceive('assertCanDisable')->byDefault();

    [$directory, $path, $repo, $syncer, , $migrator, , , $lifecycle] = moduleRepositoryFixture(dependencies: $dependencies);

    $lifecycle->shouldReceive('activating')->never();
    $migrator->shouldReceive('migrate')->never();
    $syncer->shouldReceive('sync')->never();

    try {
        expect(fn () => $repo->enable('inengine/alert'))
            ->toThrow(UnmetModuleDependencyException::class, 'Cannot enable [inengine/alert]');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('refuses to disable when dependency guard reports enabled dependents', function () {
    $dependencies = Mockery::mock(ModuleDependencyGuard::class);
    $dependencies->shouldReceive('assertCanEnable')->byDefault();
    $dependencies->shouldReceive('assertCanDisable')
        ->once()
        ->with('inengine/users')
        ->andThrow(new ModuleHasDependentsException('inengine/users', ['inengine/alert']));

    [$directory, $path, $repo, $syncer, , , , , $lifecycle] = moduleRepositoryFixture(dependencies: $dependencies);

    $lifecycle->shouldReceive('activating')->once();
    $lifecycle->shouldReceive('activated')->once();
    $lifecycle->shouldReceive('deactivating')->never();
    $syncer->shouldReceive('sync')->twice();

    try {
        $repo->enable('inengine/users');

        expect(fn () => $repo->disable('inengine/users'))
            ->toThrow(ModuleHasDependentsException::class, 'enabled dependents');
        expect($repo->isEnabled('inengine/users'))->toBeTrue();
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('refuses to forget when dependency guard reports enabled dependents', function () {
    $dependencies = Mockery::mock(ModuleDependencyGuard::class);
    $dependencies->shouldReceive('assertCanEnable')->byDefault();
    $dependencies->shouldReceive('assertCanDisable')
        ->once()
        ->with('inengine/users')
        ->andThrow(new ModuleHasDependentsException('inengine/users', ['inengine/alert']));

    [$directory, $path, $repo, , , , , , $lifecycle] = moduleRepositoryFixture(dependencies: $dependencies);

    $lifecycle->shouldReceive('uninstalling')->never();

    try {
        $repo->register('inengine/users');

        expect(fn () => $repo->forget('inengine/users'))
            ->toThrow(ModuleHasDependentsException::class);
        expect($repo->get('inengine/users'))->not->toBeNull();
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('dispatches lifecycle events for register enable disable forget', function () {
    $events = Mockery::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(fn ($e) => $e instanceof ModuleRegistered
            && $e->module->package === 'inengine/alert'
            && $e->module->enabled === false));
    $events->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(fn ($e) => $e instanceof ModuleEnabled
            && $e->module->package === 'inengine/alert'
            && $e->module->enabled === true));
    $events->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(fn ($e) => $e instanceof ModuleDisabled
            && $e->module->package === 'inengine/alert'
            && $e->module->enabled === false));
    $events->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(fn ($e) => $e instanceof ModuleForgotten
            && $e->package === 'inengine/alert'));

    [$directory, $path, $repo] = moduleRepositoryFixture(events: $events);

    try {
        $repo->register('inengine/alert', '1.0.0');
        $repo->enable('inengine/alert');
        $repo->disable('inengine/alert');
        $repo->forget('inengine/alert');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('dispatches ModuleUpgraded when an enabled package version changes', function () {
    $events = Mockery::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::type(ModuleRegistered::class));
    $events->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::type(ModuleEnabled::class));
    $events->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(fn ($e) => $e instanceof ModuleUpgraded
            && $e->module->package === 'inengine/alert'
            && $e->previousVersion === '1.0.0'
            && $e->module->version === '1.1.0'));

    [$directory, $path, $repo] = moduleRepositoryFixture(events: $events);

    try {
        $repo->register('inengine/alert', '1.0.0');
        $repo->enable('inengine/alert');
        $repo->register('inengine/alert', '1.1.0');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('dispatches ModulePublished and ModuleSeeded after successful operations', function () {
    $events = Mockery::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->once()->with(Mockery::type(ModulePublished::class));
    $events->shouldReceive('dispatch')->once()->with(Mockery::type(ModuleSeeded::class));

    $seeder = Mockery::mock(ModuleSeeder::class);
    $seeder->shouldReceive('requiresConfirmation')->andReturn(false);
    $seeder->shouldReceive('run')->once();

    [$directory, $path, $repo, , , , $publisher, $seeders] = moduleRepositoryFixture(events: $events);

    $publisher->shouldReceive('publish')->once()->with('inengine/alert');
    $seeders->shouldReceive('resolve')->once()->with('inengine/alert')->andReturn($seeder);

    try {
        $repo->publish('inengine/alert');
        $repo->seed('inengine/alert');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('discover registers modulus-like installed packages', function () {
    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('isInstalled')->andReturn(true)->byDefault();
    $locator->shouldReceive('installPath')->andReturn(null)->byDefault();
    $locator->shouldReceive('composerRequires')->andReturn([])->byDefault();
    $locator->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();
    $locator->shouldReceive('installedPackageNames')->andReturn(['inengine/alert', 'guzzlehttp/guzzle']);
    $locator->shouldReceive('version')->with('inengine/alert')->andReturn('2.0.0');
    $locator->shouldReceive('version')->with('guzzlehttp/guzzle')->andReturn('7.0.0')->byDefault();
    $locator->shouldReceive('rootConstraint')->andReturn(null)->byDefault();

    // Pretend alert is already tracked so looksLikeModulusModule is true without providers.
    [$directory, $path, $repo] = moduleRepositoryFixture(installedPackages: $locator);

    try {
        $repo->register('inengine/alert', '1.0.0');
        $count = $repo->discover();

        expect($count)->toBeGreaterThanOrEqual(1)
            ->and($repo->get('inengine/alert')['version'])->toBe('2.0.0');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('upgrade applies installed version for an enabled package', function () {
    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('isInstalled')->andReturn(true)->byDefault();
    $locator->shouldReceive('installPath')->andReturn(null)->byDefault();
    $locator->shouldReceive('composerRequires')->andReturn([])->byDefault();
    $locator->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();
    $locator->shouldReceive('installedPackageNames')->andReturn([])->byDefault();
    $locator->shouldReceive('version')->with('inengine/alert')->andReturn('1.2.0');
    $locator->shouldReceive('rootConstraint')->andReturn(null)->byDefault();

    [$directory, $path, $repo, , , $migrator, , , $lifecycle] = moduleRepositoryFixture(installedPackages: $locator);

    $lifecycle->shouldReceive('activating')->once();
    $lifecycle->shouldReceive('activated')->once();
    $lifecycle->shouldReceive('upgrading')->once();
    $lifecycle->shouldReceive('upgraded')->once();
    $migrator->shouldReceive('migrate')->twice();

    try {
        $repo->enable('inengine/alert');
        // Force older state version then upgrade to installed 1.2.0
        $store = new ReflectionProperty($repo, 'store');
        $state = $store->getValue($repo)->get('inengine/alert');
        $state['version'] = '1.0.0';
        $store->getValue($repo)->put('inengine/alert', $state);

        expect($repo->upgrade('inengine/alert'))->toBeTrue()
            ->and($repo->get('inengine/alert')['version'])->toBe('1.2.0');
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});

it('does not re-dispatch ModuleEnabled when enabling an already-enabled package', function () {
    $dispatched = [];
    $events = Mockery::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->andReturnUsing(function (object $event) use (&$dispatched): void {
        $dispatched[] = $event::class;
    });

    [$directory, $path, $repo] = moduleRepositoryFixture(events: $events);

    try {
        $repo->enable('inengine/alert');
        $repo->enable('inengine/alert');

        expect($dispatched)->toBe([ModuleRegistered::class, ModuleEnabled::class]);
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});
