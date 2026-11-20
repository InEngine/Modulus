<?php

namespace InEngine\Modulus;

use InEngine\Modulus\Capabilities\CapabilityRegistry;
use InEngine\Modulus\Capabilities\ModuleCapabilityLocator;
use InEngine\Modulus\Commands\CapabilitiesCommand;
use InEngine\Modulus\Commands\DisableModuleCommand;
use InEngine\Modulus\Commands\DiscoverModulesCommand;
use InEngine\Modulus\Commands\DoctorCommand;
use InEngine\Modulus\Commands\EnableModuleCommand;
use InEngine\Modulus\Commands\ListModulesCommand;
use InEngine\Modulus\Commands\OutdatedModulesCommand;
use InEngine\Modulus\Commands\PublishModuleCommand;
use InEngine\Modulus\Commands\SeedModuleCommand;
use InEngine\Modulus\Commands\StatusModuleCommand;
use InEngine\Modulus\Commands\UninstallModuleCommand;
use InEngine\Modulus\Commands\UpdateModulesCommand;
use InEngine\Modulus\Commands\UpgradeModulesCommand;
use InEngine\Modulus\Composer\ComposerInstalledPackageLocator;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleDependencyGuard;
use InEngine\Modulus\Contracts\ModuleLifecycleRunner;
use InEngine\Modulus\Contracts\ModuleMigrator;
use InEngine\Modulus\Contracts\ModulePublisher;
use InEngine\Modulus\Contracts\ModuleSeederRunner;
use InEngine\Modulus\Contracts\ModuleUpdateApplier;
use InEngine\Modulus\Contracts\ModuleUpdateChecker;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Contracts\PackageDiscoverySyncer;
use InEngine\Modulus\Dependencies\ComposerModuleDependencyGuard;
use InEngine\Modulus\Diagnostics\ModulusDoctor;
use InEngine\Modulus\Discovery\FilteringPackageDiscoverySyncer;
use InEngine\Modulus\Lifecycle\ResolvingModuleLifecycleRunner;
use InEngine\Modulus\Migration\PathModuleMigrator;
use InEngine\Modulus\Publish\PathModulePublisher;
use InEngine\Modulus\Seeding\ResolvingModuleSeederRunner;
use InEngine\Modulus\State\ModulusStateStoreResolver;
use InEngine\Modulus\Updates\ArrayModuleUpdateChecker;
use InEngine\Modulus\Updates\ComposerOutdatedUpdateChecker;
use InEngine\Modulus\Updates\ModuleUpdateNotifier;
use InEngine\Modulus\Updates\NullModuleUpdateApplier;
use InEngine\Modulus\Upgrades\SemverBumpClassifier;
use InEngine\Modulus\Upgrades\UpgradeConstraintPolicy;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Package service provider for InEngine Modulus.
 *
 * Registers config, views, Artisan commands, and container bindings (state store,
 * discovery syncer, migrator, publisher, seeder, lifecycle, dependency guard,
 * {@see ModuleRepository}, upgrade policy, update checker/notifier, and doctor).
 */
class ModulusServiceProvider extends PackageServiceProvider
{
    /**
     * Declare the package name, config file, views, and Artisan commands.
     *
     * @param  Package  $package  Spatie package-tools builder.
     */
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
            ->hasCommands([
                ListModulesCommand::class,
                DiscoverModulesCommand::class,
                EnableModuleCommand::class,
                DisableModuleCommand::class,
                UninstallModuleCommand::class,
                StatusModuleCommand::class,
                PublishModuleCommand::class,
                SeedModuleCommand::class,
                UpgradeModulesCommand::class,
                OutdatedModulesCommand::class,
                UpdateModulesCommand::class,
                DoctorCommand::class,
                CapabilitiesCommand::class,
            ]);
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

        $this->app->singleton(ModuleCapabilityLocator::class);

        $this->app->singleton(CapabilityRegistry::class);

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
                $app['events'],
                $app->make(ModuleCapabilityLocator::class),
                $app['config'],
            );
        });

        $this->app->singleton(UpgradeConstraintPolicy::class, function ($app): UpgradeConstraintPolicy {
            return UpgradeConstraintPolicy::fromConfig(
                $app['config']->get('modulus.upgrades.default_constraint', 'patch'),
            );
        });

        $this->app->singleton(SemverBumpClassifier::class);

        $this->app->singleton(ModuleUpdateChecker::class, function ($app): ModuleUpdateChecker {
            if ($app['config']->get('modulus.updates.check', true) !== true) {
                return new ArrayModuleUpdateChecker([]);
            }

            return new ComposerOutdatedUpdateChecker(
                $app->make(ModulusStateStore::class),
                $app->make(InstalledPackageLocator::class),
                $app->make(SemverBumpClassifier::class),
                base_path(),
            );
        });

        $this->app->singleton(ModuleUpdateApplier::class, NullModuleUpdateApplier::class);

        $this->app->singleton(ModuleUpdateNotifier::class, function ($app): ModuleUpdateNotifier {
            return new ModuleUpdateNotifier(
                $app->make(ModuleUpdateChecker::class),
                $app->make(ModuleUpdateApplier::class),
                $app->make(ModuleRepository::class),
                $app['events'],
                (bool) $app['config']->get('modulus.updates.auto_patch', false),
            );
        });

        $this->app->singleton(ModulusDoctor::class, function ($app): ModulusDoctor {
            $deny = $app['config']->get('modulus.discovery.deny', ['inengine/modulus']);

            if (! is_array($deny)) {
                $deny = ['inengine/modulus'];
            }

            /** @var list<string> $denyList */
            $denyList = array_values(array_filter($deny, 'is_string'));

            $includeUpdates = (bool) $app['config']->get('modulus.updates.include_in_doctor', true)
                && (bool) $app['config']->get('modulus.updates.check', true);

            return new ModulusDoctor(
                $app->make(ModulusStateStore::class),
                $app->make(InstalledPackageLocator::class),
                $app->make(ModuleDependencyGuard::class),
                $app->make(UpgradeConstraintPolicy::class),
                $includeUpdates ? $app->make(ModuleUpdateChecker::class) : null,
                $includeUpdates,
                $denyList,
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
