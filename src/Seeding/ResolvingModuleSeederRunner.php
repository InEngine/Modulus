<?php

namespace InEngine\Modulus\Seeding;

use Illuminate\Contracts\Foundation\Application;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleSeeder;
use InEngine\Modulus\Contracts\ModuleSeederRunner;
use InEngine\Modulus\Exceptions\ModuleSeederMissingException;
use InEngine\Modulus\ModuleServiceProvider;

/**
 * Resolves {@see ModuleSeeder} via the module's {@see ModuleServiceProvider}.
 *
 * Instantiates the provider from Composer extra.laravel.providers (same pattern
 * as lifecycle hooks) so seeding works even when the module is disabled.
 */
final class ResolvingModuleSeederRunner implements ModuleSeederRunner
{
    public function __construct(
        protected Application $app,
        protected InstalledPackageLocator $packages,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function resolve(string $package): ModuleSeeder
    {
        foreach ($this->packages->laravelProviderClasses($package) as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, ModuleServiceProvider::class)) {
                continue;
            }

            /** @var ModuleServiceProvider $provider */
            $provider = new $class($this->app);

            /** @var mixed $seederClass */
            $seederClass = $provider->modulusSeederClass();

            if (! is_string($seederClass) || $seederClass === '' || ! class_exists($seederClass)) {
                throw new ModuleSeederMissingException($package);
            }

            if (! is_a($seederClass, ModuleSeeder::class, true)) {
                throw new ModuleSeederMissingException($package);
            }

            $seeder = $this->app->make($seederClass);

            if (! $seeder instanceof ModuleSeeder) {
                throw new ModuleSeederMissingException($package);
            }

            return $seeder;
        }

        throw new ModuleSeederMissingException($package);
    }
}
