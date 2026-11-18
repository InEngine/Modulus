<?php

namespace InEngine\Modulus;

use InEngine\Modulus\Commands\ModulusCommand;
use InEngine\Modulus\Composer\ComposerInstalledPackageLocator;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleDependencyGuard;
use InEngine\Modulus\Contracts\ModuleLifecycleRunner;
use InEngine\Modulus\Contracts\ModuleMigrator;
use InEngine\Modulus\Contracts\ModulePublisher;
use InEngine\Modulus\Contracts\ModuleSeederRunner;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Contracts\PackageDiscoverySyncer;
use InEngine\Modulus\Dependencies\ComposerModuleDependencyGuard;
use InEngine\Modulus\Discovery\FilteringPackageDiscoverySyncer;
use InEngine\Modulus\Lifecycle\ResolvingModuleLifecycleRunner;
use InEngine\Modulus\Migration\PathModuleMigrator;
use InEngine\Modulus\Publish\PathModulePublisher;
use InEngine\Modulus\Seeding\ResolvingModuleSeederRunner;
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
     * Bind store, discovery syncer, migrator, lifecycle runner, and repository.
     */
    public function packageRegistered(): void
    {
        $this->app->singleton(ModulusStateStore::class, function ($app): ModulusStateStore {
            return ModulusStateStoreResolver::resolve($app);
        });

        $this->app->singleton(PackageDiscoverySyncer::class, FilteringPackageDiscoverySyncer::class);

        $this->app->singleton(InstalledPackageLocator::class, ComposerInstalledPackageLocator::class);

        $this->app->singleton(ModuleMigrator::class, function ($app): ModuleMigrator {
            return PathModuleMigrator::fromConfig(
                $app->make(InstalledPackageLocator::class),
                $app,
                $app['config'],
            );
        });

        $this->app->singleton(ModulePublisher::class, function ($app): ModulePublisher {
            return PathModulePublisher::fromConfig(
                $app->make(InstalledPackageLocator::class),
                $app->make('files'),
                $app['config'],
            );
        });

        $this->app->singleton(ModuleSeederRunner::class, ResolvingModuleSeederRunner::class);

        $this->app->singleton(ModuleLifecycleRunner::class, ResolvingModuleLifecycleRunner::class);

        $this->app->singleton(ModuleDependencyGuard::class, function ($app): ModuleDependencyGuard {
            $deny = $app['config']->get('modulus.discovery.deny', ['inengine/modulus']);

            if (! is_array($deny)) {
                $deny = ['inengine/modulus'];
            }

            /** @var list<string> $denyList */
            $denyList = array_values(array_filter($deny, 'is_string'));

            return new ComposerModuleDependencyGuard(
                $app->make(ModulusStateStore::class),
                $app->make(InstalledPackageLocator::class),
                $denyList,
            );
        });

        $this->app->singleton(ModuleRepository::class, function ($app): ModuleRepository {
            return ModuleRepository::fromConfig(
                $app->make(ModulusStateStore::class),
                $app->make(PackageDiscoverySyncer::class),
                $app->make(InstalledPackageLocator::class),
                $app->make(ModuleMigrator::class),
                $app->make(ModulePublisher::class),
                $app->make(ModuleSeederRunner::class),
                $app->make(ModuleLifecycleRunner::class),
                $app->make(ModuleDependencyGuard::class),
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
