<?php

namespace InEngine\Modulus;

use InEngine\Modulus\Commands\ModulusCommand;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Contracts\PackageDiscoverySyncer;
use InEngine\Modulus\Discovery\FilteringPackageDiscoverySyncer;
use InEngine\Modulus\State\FileModulusStateStore;
use InEngine\Modulus\State\FileModulusStateStoreFactory;
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
            ->hasConfigFile('modulus')
            ->hasViews()
            ->hasCommand(ModulusCommand::class);
    }

    /**
     * Bind store, discovery syncer, and module repository.
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
                return FileModulusStateStoreFactory::make($app['config'], $app['files']);
            }

            $store = $app->make($storeClass);

            if (! $store instanceof ModulusStateStore) {
                throw new \InvalidArgumentException(
                    'Resolved [modulus.store] instance must implement '.ModulusStateStore::class.'.'
                );
            }

            return $store;
        });

        $this->app->singleton(PackageDiscoverySyncer::class, FilteringPackageDiscoverySyncer::class);

        $this->app->singleton(ModuleRepository::class, function ($app): ModuleRepository {
            return ModuleRepository::fromConfig(
                $app->make(ModulusStateStore::class),
                $app->make(PackageDiscoverySyncer::class),
                $app['config'],
            );
        });
    }
}
