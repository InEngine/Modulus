<?php

/**
 * Unit coverage for {@see \InEngine\Modulus\Lifecycle\ResolvingModuleLifecycleRunner} lifecycle hook dispatch to module providers.
 */

use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Lifecycle\ResolvingModuleLifecycleRunner;
use InEngine\Modulus\ModuleServiceProvider;
use Spatie\LaravelPackageTools\Package;

it('invokes lifecycle hooks on a resolvable ModuleServiceProvider', function () {
    $providerClass = new class(app()) extends ModuleServiceProvider
    {
        /** @var list<string> */
        public static array $calls = [];

        public function configurePackage(Package $package): void
        {
            $package->name('alert');
        }

        protected function modulusPackageName(): string
        {
            return 'inengine/alert';
        }

        public function activating(string $package): void
        {
            self::$calls[] = "activating:{$package}";
        }

        public function activated(string $package): void
        {
            self::$calls[] = "activated:{$package}";
        }

        public function deactivating(string $package): void
        {
            self::$calls[] = "deactivating:{$package}";
        }

        public function deactivated(string $package): void
        {
            self::$calls[] = "deactivated:{$package}";
        }

        public function uninstalling(string $package): void
        {
            self::$calls[] = "uninstalling:{$package}";
        }

        public function uninstalled(string $package): void
        {
            self::$calls[] = "uninstalled:{$package}";
        }

        public function upgrading(string $package): void
        {
            self::$calls[] = "upgrading:{$package}";
        }

        public function upgraded(string $package): void
        {
            self::$calls[] = "upgraded:{$package}";
        }
    };

    $className = $providerClass::class;
    $providerClass::$calls = [];

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('laravelProviderClasses')
        ->with('inengine/alert')
        ->andReturn([$className]);

    $runner = new ResolvingModuleLifecycleRunner(app(), $locator);

    $runner->activating('inengine/alert');
    $runner->activated('inengine/alert');
    $runner->deactivating('inengine/alert');
    $runner->deactivated('inengine/alert');
    $runner->uninstalling('inengine/alert');
    $runner->uninstalled('inengine/alert');
    $runner->upgrading('inengine/alert');
    $runner->upgraded('inengine/alert');

    expect($providerClass::$calls)->toBe([
        'activating:inengine/alert',
        'activated:inengine/alert',
        'deactivating:inengine/alert',
        'deactivated:inengine/alert',
        'uninstalling:inengine/alert',
        'uninstalled:inengine/alert',
        'upgrading:inengine/alert',
        'upgraded:inengine/alert',
    ]);
});

it('no-ops when no Modulus-aware provider is listed', function () {
    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('laravelProviderClasses')
        ->with('vendor/plain')
        ->andReturn(['stdClass']);

    $runner = new ResolvingModuleLifecycleRunner(app(), $locator);

    $runner->activating('vendor/plain');
    $runner->activated('vendor/plain');

    expect(true)->toBeTrue();
});

it('no-ops when the package has no laravel providers', function () {
    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('laravelProviderClasses')
        ->with('inengine/missing')
        ->andReturn([]);

    $runner = new ResolvingModuleLifecycleRunner(app(), $locator);

    $runner->uninstalling('inengine/missing');
    $runner->uninstalled('inengine/missing');

    expect(true)->toBeTrue();
});
