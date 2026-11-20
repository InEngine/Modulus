<?php

/**
 * Unit coverage for {@see \InEngine\Modulus\Seeding\ResolvingModuleSeederRunner} seeder resolution from module providers.
 */

use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleSeeder;
use InEngine\Modulus\Exceptions\ModuleSeederMissingException;
use InEngine\Modulus\ModuleServiceProvider;
use InEngine\Modulus\Seeding\ResolvingModuleSeederRunner;
use Spatie\LaravelPackageTools\Package;

it('resolves the seeder class declared on the ModuleServiceProvider', function () {
    $seeder = new class implements ModuleSeeder
    {
        public bool $ran = false;

        public function requiresConfirmation(): bool
        {
            return true;
        }

        public function run(): void
        {
            $this->ran = true;
        }
    };

    $seederClass = $seeder::class;

    $provider = new class(app()) extends ModuleServiceProvider
    {
        public static ?string $seederFqcn = null;

        public function configurePackage(Package $package): void
        {
            $package->name('alert');
        }

        protected function modulusPackageName(): string
        {
            return 'inengine/alert';
        }

        public function modulusSeederClass(): ?string
        {
            return self::$seederFqcn;
        }
    };

    $provider::$seederFqcn = $seederClass;

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('laravelProviderClasses')
        ->with('inengine/alert')
        ->andReturn([$provider::class]);

    app()->instance($seederClass, $seeder);

    $resolved = (new ResolvingModuleSeederRunner(app(), $locator))->resolve('inengine/alert');

    expect($resolved->requiresConfirmation())->toBeTrue();
    $resolved->run();
    expect($seeder->ran)->toBeTrue();
});

it('throws when the provider has no seeder class', function () {
    $provider = new class(app()) extends ModuleServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('alert');
        }

        protected function modulusPackageName(): string
        {
            return 'inengine/alert';
        }
    };

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('laravelProviderClasses')
        ->with('inengine/alert')
        ->andReturn([$provider::class]);

    $runner = new ResolvingModuleSeederRunner(app(), $locator);

    expect(fn () => $runner->resolve('inengine/alert'))
        ->toThrow(ModuleSeederMissingException::class);
});
