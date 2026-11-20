<?php

namespace InEngine\Modulus;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleDependencyGuard;
use InEngine\Modulus\Contracts\ModuleLifecycleRunner;
use InEngine\Modulus\Contracts\ModuleMigrator;
use InEngine\Modulus\Contracts\ModulePublisher;
use InEngine\Modulus\Contracts\ModuleSeeder;
use InEngine\Modulus\Contracts\ModuleSeederRunner;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Contracts\PackageDiscoverySyncer;
use InEngine\Modulus\DataTransferObjects\ModuleStateData;
use InEngine\Modulus\Events\ModuleDisabled;
use InEngine\Modulus\Events\ModuleEnabled;
use InEngine\Modulus\Events\ModuleForgotten;
use InEngine\Modulus\Events\ModuleManualAttentionRequired;
use InEngine\Modulus\Events\ModulePublished;
use InEngine\Modulus\Events\ModuleRegistered;
use InEngine\Modulus\Events\ModuleSeeded;
use InEngine\Modulus\Events\ModuleUpgraded;
use InEngine\Modulus\Exceptions\MissingModuleException;
use InEngine\Modulus\Exceptions\ModuleHasDependentsException;
use InEngine\Modulus\Exceptions\ModuleSeederMissingException;
use InEngine\Modulus\Exceptions\SeedingConfirmationRequiredException;
use InEngine\Modulus\Exceptions\UnmetModuleDependencyException;

/**
 * Orchestrates module registration, lifecycle, publish, migrations, seed, and toggles.
 *
 * Facade and Artisan commands should call this class only — not the store,
 * publisher, migrator, seeder runner, lifecycle runner, or discovery syncer directly.
 *
 * Step 6 pipeline (non-destructive disable / forget):
 * - enable: dependency check → activating → migrate → state enabled → sync → activated
 * - disable: dependent check → deactivating → state disabled → sync → deactivated
 * - forget / uninstall: dependent check → disable if needed → uninstalling → remove state → sync → uninstalled
 * - register version bump while enabled: upgrading → migrate → upgraded
 * - publish: explicit {@see publish()} for install/update — owned by modulus-composer
 * - seed: explicit {@see seed()} with confirmation — never on enable/install
 *
 * Step 7 deps: Composer `require` is the hard graph. Modulus modules among
 * requires must be enabled; plain Composer packages must only be installed
 * (the feature module may optionally reach down for publish/migrate).
 *
 * Step 8: Laravel events after successful transitions ({@see ModuleEnabled}, …).
 * Owning-module hooks stay on {@see ModuleLifecycle}; events are for peers/host.
 *
 * Composer require/remove stays in inengine/modulus-composer (Step 10). That
 * extension should call {@see publish()} after acquire/update (and before enable).
 *
 * @phpstan-import-type ModuleState from ModulusStateStore
 */
class ModuleRepository
{
    /**
     * @param  list<string>  $deny
     */
    public function __construct(
        protected ModulusStateStore $store,
        protected PackageDiscoverySyncer $discovery,
        protected InstalledPackageLocator $installedPackages,
        protected ModuleMigrator $migrator,
        protected ModulePublisher $publisher,
        protected ModuleSeederRunner $seeders,
        protected ModuleLifecycleRunner $lifecycle,
        protected ModuleDependencyGuard $dependencies,
        protected Dispatcher $events,
        protected array $deny = ['inengine/modulus'],
    ) {}

    /**
     * Build from container config (deny list).
     */
    public static function fromConfig(
        ModulusStateStore $store,
        PackageDiscoverySyncer $discovery,
        InstalledPackageLocator $installedPackages,
        ModuleMigrator $migrator,
        ModulePublisher $publisher,
        ModuleSeederRunner $seeders,
        ModuleLifecycleRunner $lifecycle,
        ModuleDependencyGuard $dependencies,
        Dispatcher $events,
        ConfigRepository $config,
    ): self {
        $deny = $config->get('modulus.discovery.deny', ['inengine/modulus']);

        if (! is_array($deny)) {
            $deny = ['inengine/modulus'];
        }

        /** @var list<string> $denyList */
        $denyList = array_values(array_filter($deny, 'is_string'));

        return new self($store, $discovery, $installedPackages, $migrator, $publisher, $seeders, $lifecycle, $dependencies, $events, $denyList);
    }

    /**
     * @return array<string, ModuleState>
     */
    public function all(): array
    {
        return $this->store->all();
    }

    /**
     * @return array<string, ModuleState>
     */
    public function enabled(): array
    {
        return array_filter(
            $this->all(),
            fn (array $state): bool => $state['enabled'] === true,
        );
    }

    /**
     * @return array<string, ModuleState>
     */
    public function disabled(): array
    {
        return array_filter(
            $this->all(),
            fn (array $state): bool => $state['enabled'] === false,
        );
    }

    /**
     * @return ModuleState|null
     */
    public function get(string $package): ?array
    {
        return $this->store->get($package);
    }

    /** Whether a package is enabled; denied packages (e.g. Modulus itself) always count as enabled. */
    public function isEnabled(string $package): bool
    {
        if ($this->isDenied($package)) {
            return true;
        }

        return $this->store->isEnabled($package);
    }

    /**
     * Track a package in state. New packages default to disabled.
     *
     * When an already-enabled package reports a new version, run the upgrade
     * lifecycle (upgrading → migrate → upgraded) before persisting.
     *
     * Resource republish after Composer update is {@see publish()} via
     * modulus-composer — not this handshake path.
     */
    public function register(string $package, ?string $version = null): void
    {
        $existing = $this->store->get($package);

        if ($existing === null) {
            $this->store->put($package, [
                'enabled' => $this->isDenied($package),
                'version' => $version,
            ]);
            $this->discovery->sync();
            $this->dispatchRegistered($package);

            return;
        }

        if ($version !== null && $existing['version'] !== $version) {
            $wasEnabled = $existing['enabled'] === true;
            $previousVersion = $existing['version'];
            $existing['version'] = $version;
            $this->store->put($package, $existing);

            if ($wasEnabled) {
                $this->lifecycle->upgrading($package);
                $this->migrator->migrate($package);
                $this->lifecycle->upgraded($package);
                $this->discovery->sync();
                $this->events->dispatch(new ModuleUpgraded(
                    $this->stateData($package),
                    $previousVersion,
                ));
                $this->dispatchManualAttentionIfAny($package);

                return;
            }

            $this->discovery->sync();
        }
    }

    /**
     * Scan Composer installs for Modulus modules and {@see register()} each.
     *
     * @return int Number of packages newly tracked or version-updated
     */
    public function discover(): int
    {
        $count = 0;

        foreach ($this->installedPackages->installedPackageNames() as $package) {
            if (! $this->looksLikeModulusModule($package)) {
                continue;
            }

            $this->register($package, $this->installedPackages->version($package));
            $count++;
        }

        $this->discovery->sync();

        return $count;
    }

    /**
     * Apply the installed Composer version for a package (migrate/hooks if enabled).
     *
     * Optional republish for host config/assets. Approving a new minor/major
     * constraint still belongs to modulus-composer / UI (rewrites composer.json).
     *
     * @return bool True when a version change was applied (or publish ran)
     */
    public function upgrade(string $package, bool $publish = false): bool
    {
        if ($this->isDenied($package)) {
            return false;
        }

        $this->assertPackageIsInstalled($package, wasEnabled: $this->store->isEnabled($package));

        $before = $this->store->get($package);
        $version = $this->installedPackages->version($package);
        $this->register($package, $version);
        $after = $this->store->get($package);

        $changed = $before === null
            || ($version !== null && ($before['version'] ?? null) !== ($after['version'] ?? null));

        if ($publish) {
            $this->publish($package);
            $changed = true;
        }

        return $changed;
    }

    /**
     * Upgrade every tracked installed package (optional publish).
     *
     * @return int Number of packages that changed
     */
    public function upgradeAll(bool $publish = false): int
    {
        $count = 0;

        foreach (array_keys($this->store->all()) as $package) {
            if ($this->isDenied($package) || ! $this->installedPackages->isInstalled($package)) {
                continue;
            }

            if ($this->upgrade($package, $publish)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Publish conventional module resources into the host (config, assets, opt-in UI).
     *
     * Intended for the **install / Composer update** path (`inengine/modulus-composer`),
     * not enable/disable. Install ≠ enable: require → publish → discover (disabled) →
     * explicit enable (migrate + boot).
     *
     * @throws MissingModuleException When the Composer package is not installed.
     */
    public function publish(string $package): void
    {
        if ($this->isDenied($package)) {
            return;
        }

        $this->assertPackageIsInstalled($package, wasEnabled: false);
        $this->publisher->publish($package);
        $this->events->dispatch(new ModulePublished($package));
    }

    /**
     * Run the module's {@see ModuleSeeder} explicitly.
     *
     * Never called by enable/install. Skeleton seeders require confirmation:
     * pass confirmed: true only after an operator OK (Artisan will prompt later).
     *
     * @throws MissingModuleException
     * @throws SeedingConfirmationRequiredException
     * @throws ModuleSeederMissingException
     */
    public function seed(string $package, bool $confirmed = false): void
    {
        if ($this->isDenied($package)) {
            return;
        }

        $this->assertPackageIsInstalled($package, wasEnabled: false);

        $seeder = $this->seeders->resolve($package);

        if ($seeder->requiresConfirmation() && ! $confirmed) {
            throw new SeedingConfirmationRequiredException($package);
        }

        $seeder->run();
        $this->events->dispatch(new ModuleSeeded($package));
    }

    /**
     * Enable a tracked package (registers first when unknown).
     *
     * Order: activating → migrate → state → discovery sync → activated.
     * Already-enabled packages re-run pending migrations (idempotent hooks).
     * Denied packages stay permanently enabled (register only).
     *
     * Does **not** publish config/assets — call {@see publish()} from install
     * (modulus-composer) or manually before first enable when UI assets are required.
     * Does **not** seed — call {@see seed()} with confirmation explicitly.
     *
     * @throws MissingModuleException When the Composer package is not installed.
     * @throws UnmetModuleDependencyException When Composer requires are unmet.
     */
    public function enable(string $package): void
    {
        if ($this->isDenied($package)) {
            $this->register($package);

            return;
        }

        $this->assertPackageIsInstalled($package, wasEnabled: true);

        if ($this->store->isEnabled($package)) {
            $this->migrator->migrate($package);

            return;
        }

        $this->dependencies->assertCanEnable($package);
        $this->lifecycle->activating($package);
        $this->migrator->migrate($package);
        $this->register($package);
        $this->store->setEnabled($package, true);
        $this->discovery->sync();
        $this->lifecycle->activated($package);
        $this->events->dispatch(new ModuleEnabled($this->stateData($package)));
    }

    /**
     * Ensure tracked packages still exist in Composer.
     *
     * Enabled + missing → throws. Disabled + missing → exception is thrown then
     * reported to the Laravel log without aborting boot.
     *
     * @throws MissingModuleException When an enabled tracked package is not installed.
     */
    public function validateTrackedPackages(): void
    {
        foreach ($this->store->all() as $package => $state) {
            if ($this->isDenied($package) || $this->installedPackages->isInstalled($package)) {
                continue;
            }

            $exception = new MissingModuleException($package, $state['enabled']);

            if ($state['enabled']) {
                throw $exception;
            }

            try {
                throw $exception;
            } catch (MissingModuleException $e) {
                Log::warning($e->getMessage(), [
                    'package' => $package,
                    'enabled' => false,
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * @throws MissingModuleException
     */
    protected function assertPackageIsInstalled(string $package, bool $wasEnabled): void
    {
        if ($this->installedPackages->isInstalled($package)) {
            return;
        }

        throw new MissingModuleException($package, $wasEnabled);
    }

    /**
     * Disable a tracked package. Denied packages cannot be disabled.
     *
     * Non-destructive: leaves DB tables and module storage intact.
     * Order: deactivating → state → discovery sync → deactivated.
     * Already-disabled packages are a no-op after ensuring they are tracked.
     *
     * @throws \InvalidArgumentException
     * @throws ModuleHasDependentsException When enabled modules still require this package.
     */
    public function disable(string $package): void
    {
        if ($this->isDenied($package)) {
            throw new \InvalidArgumentException(
                "Package [{$package}] is on the Modulus discovery deny list and cannot be disabled."
            );
        }

        if (! $this->store->isEnabled($package)) {
            $this->register($package);

            return;
        }

        $this->dependencies->assertCanDisable($package);
        $this->lifecycle->deactivating($package);
        $this->store->setEnabled($package, false);
        $this->discovery->sync();
        $this->lifecycle->deactivated($package);
        $this->events->dispatch(new ModuleDisabled($this->stateData($package)));
    }

    /**
     * Drop a package from Modulus state (does not run composer remove).
     *
     * Non-destructive of module data. If the package is enabled, runs disable
     * hooks first. Composer remove belongs to inengine/modulus-composer.
     *
     * @throws \InvalidArgumentException
     * @throws ModuleHasDependentsException When enabled modules still require this package.
     */
    public function forget(string $package): void
    {
        if ($this->isDenied($package)) {
            throw new \InvalidArgumentException(
                "Package [{$package}] is on the Modulus discovery deny list and cannot be forgotten."
            );
        }

        if ($this->store->get($package) === null) {
            return;
        }

        // Dependents block forget even when already disabled — state removal would
        // leave enabled dependents with a Composer require Modulus no longer tracks.
        $this->dependencies->assertCanDisable($package);

        if ($this->store->isEnabled($package)) {
            $this->lifecycle->deactivating($package);
            $this->store->setEnabled($package, false);
            $this->discovery->sync();
            $this->lifecycle->deactivated($package);
            $this->events->dispatch(new ModuleDisabled($this->stateData($package)));
        }

        $this->lifecycle->uninstalling($package);
        $this->store->remove($package);
        $this->discovery->sync();
        $this->lifecycle->uninstalled($package);
        $this->events->dispatch(new ModuleForgotten($package));
    }

    /**
     * Alias of {@see forget()} — uninstall-from-state without Composer I/O.
     *
     * @throws \InvalidArgumentException
     */
    public function uninstall(string $package): void
    {
        $this->forget($package);
    }

    /** Whether a package is on the discovery deny list (`modulus.discovery.deny`). */
    public function isDenied(string $package): bool
    {
        return in_array($package, $this->deny, true);
    }

    /** Dispatch {@see ModuleRegistered} for the package's current state. */
    protected function dispatchRegistered(string $package): void
    {
        $this->events->dispatch(new ModuleRegistered($this->stateData($package)));
    }

    /** Dispatch {@see ModuleManualAttentionRequired} when the module's provider reports operator items. */
    protected function dispatchManualAttentionIfAny(string $package): void
    {
        $items = [];

        foreach ($this->installedPackages->laravelProviderClasses($package) as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, ModuleServiceProvider::class)) {
                continue;
            }

            /** @var ModuleServiceProvider $provider */
            $provider = new $class(app());
            foreach ($provider->modulusManualAttentionItems() as $item) {
                if ($item !== '') {
                    $items[] = $item;
                }
            }
            break;
        }

        if ($items === []) {
            return;
        }

        $this->events->dispatch(new ModuleManualAttentionRequired(
            $package,
            $items,
            $this->store->get($package)['version'] ?? null,
        ));
    }

    /** Whether a package is tracked, denied, or ships a {@see ModuleServiceProvider}. */
    protected function looksLikeModulusModule(string $package): bool
    {
        if ($this->store->get($package) !== null || $this->isDenied($package)) {
            return true;
        }

        foreach ($this->installedPackages->laravelProviderClasses($package) as $class) {
            if (class_exists($class) && is_subclass_of($class, ModuleServiceProvider::class)) {
                return true;
            }
        }

        return false;
    }

    /** Build a {@see ModuleStateData} snapshot; untracked packages appear disabled with no version. */
    protected function stateData(string $package): ModuleStateData
    {
        $state = $this->store->get($package);

        if ($state === null) {
            return new ModuleStateData($package, enabled: false, version: null);
        }

        return ModuleStateData::fromStore($package, $state);
    }
}
