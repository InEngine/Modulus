<?php

use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleLifecycleRunner;
use InEngine\Modulus\Contracts\ModuleMigrator;
use InEngine\Modulus\Contracts\ModulePublisher;
use InEngine\Modulus\Contracts\ModuleSeederRunner;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\ModuleRepository;
use InEngine\Modulus\ModuleServiceProvider;
use InEngine\Modulus\State\FileModulusStateStore;
use Spatie\LaravelPackageTools\Package;

function makeHandshakeProvider(string $packageName = 'inengine/alert', ?string $version = null): ModuleServiceProvider
{
    return new class(app(), $packageName, $version) extends ModuleServiceProvider
    {
        public bool $packageBootedFlag = false;

        public bool $bootingPackageFlag = false;

        public function __construct($app, protected string $name, protected ?string $version)
        {
            parent::__construct($app);
        }

        public function configurePackage(Package $package): void
        {
            $package->name(str_replace('/', '-', $this->name));
        }

        protected function modulusPackageName(): string
        {
            return $this->name;
        }

        protected function modulusPackageVersion(): ?string
        {
            return $this->version;
        }

        public function bootingPackage(): void
        {
            $this->bootingPackageFlag = true;
        }

        public function packageBooted(): void
        {
            $this->packageBootedFlag = true;
        }
    };
}

function prepareModulusStatePath(): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-handshake-'.uniqid('', true).'.json';
    config()->set('modulus.state_path', $path);
    config()->set('modulus.state_fsync', false);
    config()->set('modulus.store', FileModulusStateStore::class);

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('isInstalled')->andReturn(true);
    $locator->shouldReceive('installPath')->andReturn(null)->byDefault();
    $locator->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();
    $locator->shouldReceive('composerRequires')->andReturn([])->byDefault();
    $locator->shouldReceive('installedPackageNames')->andReturn([])->byDefault();
    $locator->shouldReceive('version')->andReturn(null)->byDefault();
    $locator->shouldReceive('rootConstraint')->andReturn(null)->byDefault();
    app()->instance(InstalledPackageLocator::class, $locator);

    app()->forgetInstance(ModuleRepository::class);
    app()->forgetInstance(ModulusStateStore::class);
    app()->forgetInstance(ModuleMigrator::class);
    app()->forgetInstance(ModulePublisher::class);
    app()->forgetInstance(ModuleSeederRunner::class);
    app()->forgetInstance(ModuleLifecycleRunner::class);

    return $path;
}

function cleanupModulusStatePath(string $path): void
{
    if (is_file($path)) {
        unlink($path);
    }
}

it('registers with Modulus on packageRegistered even when disabled', function () {
    $path = prepareModulusStatePath();
    $provider = makeHandshakeProvider('inengine/alert', '1.2.3');

    try {
        $provider->register();

        $repo = app(ModuleRepository::class);

        expect($provider->modulusIsPresent())->toBeTrue()
            ->and($repo->get('inengine/alert'))->toEqual([
                'enabled' => false,
                'version' => '1.2.3',
            ])
            ->and($provider->shouldBootModule())->toBeFalse()
            ->and($provider->isEnabledInModulus())->toBeFalse();
    } finally {
        cleanupModulusStatePath($path);
    }
});

it('skips Spatie boot hooks when disabled (defense in depth)', function () {
    $path = prepareModulusStatePath();
    $provider = makeHandshakeProvider();

    try {
        $provider->register();
        $provider->boot();

        expect($provider->bootingPackageFlag)->toBeFalse()
            ->and($provider->packageBootedFlag)->toBeFalse();
    } finally {
        cleanupModulusStatePath($path);
    }
});

it('runs Spatie boot hooks when enabled in Modulus', function () {
    $path = prepareModulusStatePath();
    $provider = makeHandshakeProvider();

    try {
        $provider->register();
        app(ModuleRepository::class)->enable('inengine/alert');

        expect($provider->shouldBootModule())->toBeTrue()
            ->and($provider->isEnabledInModulus())->toBeTrue();

        $provider->boot();

        expect($provider->bootingPackageFlag)->toBeTrue()
            ->and($provider->packageBootedFlag)->toBeTrue();
    } finally {
        cleanupModulusStatePath($path);
    }
});

it('boots fully standalone when ModuleRepository is not bound', function () {
    $provider = makeHandshakeProvider('inengine/calendar');

    app()->offsetUnset(ModuleRepository::class);

    expect($provider->modulusIsPresent())->toBeFalse()
        ->and($provider->shouldBootModule())->toBeTrue()
        ->and($provider->isEnabledInModulus())->toBeFalse();

    $provider->register();
    $provider->boot();

    expect($provider->bootingPackageFlag)->toBeTrue()
        ->and($provider->packageBootedFlag)->toBeTrue();
});

it('does not register with Modulus when the runtime is absent', function () {
    $path = prepareModulusStatePath();

    try {
        // Bind store path then remove repository so handshake is standalone.
        app()->make(ModulusStateStore::class);
        app()->offsetUnset(ModuleRepository::class);

        $provider = makeHandshakeProvider('inengine/table-ui', '9.0.0');
        $provider->register();

        // Re-bind repository to inspect state file — package must not have been registered.
        app()->forgetInstance(ModulusStateStore::class);
        $store = app(ModulusStateStore::class);

        expect($store->get('inengine/table-ui'))->toBeNull();
    } finally {
        cleanupModulusStatePath($path);
    }
});
