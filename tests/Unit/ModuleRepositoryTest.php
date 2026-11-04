<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Contracts\PackageDiscoverySyncer;
use InEngine\Modulus\Exceptions\MissingModuleException;
use InEngine\Modulus\Facades\Modulus as ModulusFacade;
use InEngine\Modulus\ModuleRepository;
use InEngine\Modulus\State\FileModulusStateStore;
use Mockery\MockInterface;

/**
 * @return array{0: string, 1: string, 2: ModuleRepository, 3: MockInterface&PackageDiscoverySyncer, 4: MockInterface&InstalledPackageLocator}
 */
function moduleRepositoryFixture(
    array $deny = ['inengine/modulus'],
    ?InstalledPackageLocator $installedPackages = null,
): array {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-repo-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';
    $store = new FileModulusStateStore($path, new Filesystem, false);
    $syncer = Mockery::mock(PackageDiscoverySyncer::class);
    $syncer->shouldReceive('sync')->byDefault();

    if ($installedPackages === null) {
        $installedPackages = Mockery::mock(InstalledPackageLocator::class);
        $installedPackages->shouldReceive('isInstalled')->andReturn(true)->byDefault();
    }

    return [
        $directory,
        $path,
        new ModuleRepository($store, $syncer, $installedPackages, $deny),
        $syncer,
        $installedPackages,
    ];
}

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
    [$directory, $path, $repo, $syncer] = moduleRepositoryFixture();
    $syncer->shouldReceive('sync')->times(3);

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

it('forget removes a package and syncs discovery', function () {
    [$directory, $path, $repo, $syncer] = moduleRepositoryFixture();
    $syncer->shouldReceive('sync')->twice();

    try {
        $repo->register('inengine/alert');
        $repo->forget('inengine/alert');

        expect($repo->all())->toBe([])
            ->and($repo->get('inengine/alert'))->toBeNull();
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
    [$directory, $path, $repo, $syncer] = moduleRepositoryFixture();
    // register (new) + enable (sync) + register (version bump) = 3
    $syncer->shouldReceive('sync')->times(3);

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
        app()->instance(InstalledPackageLocator::class, $locator);

        app()->forgetInstance(ModulusStateStore::class);
        app()->forgetInstance(ModuleRepository::class);

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
