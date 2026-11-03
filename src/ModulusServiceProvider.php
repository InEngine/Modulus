<?php

namespace InEngine\Modulus;

use InEngine\Modulus\Commands\ModulusCommand;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\State\FileModulusStateStore;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ModulusServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('modulus')
            ->hasConfigFile(['modulus', 'modules'])
            ->hasViews()
            ->hasCommand(ModulusCommand::class);
    }

    /**
     * Bind the configured module state store before the package boots.
     *
     * Defaults to {@see FileModulusStateStore}. Hosts may set `modulus.store`
     * to any class that implements {@see ModulusStateStore}.
     */
    public function packageRegistered(): void
    {
        $this->app->singleton(ModulusStateStore::class, function ($app): ModulusStateStore {
            $storeClass = $app['config']->get('modulus.store', FileModulusStateStore::class);

            if (! is_string($storeClass) || $storeClass === '' || ! class_exists($storeClass)) {
                throw new \InvalidArgumentException(
                    'Config [modulus.store] must be a concrete class name implementing '.ModulusStateStore::class.'.'
                );
            }

            if (! is_a($storeClass, ModulusStateStore::class, true)) {
                throw new \InvalidArgumentException(
                    "Config [modulus.store] class [{$storeClass}] must implement ".ModulusStateStore::class.'.'
                );
            }

            if ($storeClass === FileModulusStateStore::class) {
                $path = $app['config']->get('modulus.state_path', storage_path('modulus/modules.json'));

                if (! is_string($path) || $path === '') {
                    throw new \InvalidArgumentException('Config [modulus.state_path] must be a non-empty string path.');
                }

                return new FileModulusStateStore($path);
            }

            $store = $app->make($storeClass);

            if (! $store instanceof ModulusStateStore) {
                throw new \InvalidArgumentException(
                    'Resolved [modulus.store] instance must implement '.ModulusStateStore::class.'.'
                );
            }

            return $store;
        });
    }
}
