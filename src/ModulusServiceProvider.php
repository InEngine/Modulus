<?php

namespace InEngine\Modulus;

use InEngine\Modulus\Commands\ModulusCommand;
use InEngine\Modulus\Composer\ComposerInstalledPackageLocator;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Contracts\PackageDiscoverySyncer;
use InEngine\Modulus\Discovery\FilteringPackageDiscoverySyncer;
use InEngine\Modulus\State\ModulusStateStoreResolver;
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
            return ModulusStateStoreResolver::resolve($app);
        });

        $this->app->singleton(PackageDiscoverySyncer::class, FilteringPackageDiscoverySyncer::class);

        $this->app->singleton(InstalledPackageLocator::class, ComposerInstalledPackageLocator::class);

        $this->app->singleton(ModuleRepository::class, function ($app): ModuleRepository {
            return ModuleRepository::fromConfig(
                $app->make(ModulusStateStore::class),
                $app->make(PackageDiscoverySyncer::class),
                $app->make(InstalledPackageLocator::class),
                $app['config'],
            );
        });
    }

    /**
     * After Modulus boots, fail hard on missing enabled modules and log missing disabled ones.
     */
    public function packageBooted(): void
    {
        $this->app->make(ModuleRepository::class)->validateTrackedPackages();
    }
}
