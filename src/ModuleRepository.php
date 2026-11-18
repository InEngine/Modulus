<?php

namespace InEngine\Modulus;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Log;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleLifecycleRunner;
use InEngine\Modulus\Contracts\ModuleMigrator;
use InEngine\Modulus\Contracts\ModulePublisher;
use InEngine\Modulus\Contracts\ModuleSeeder;
use InEngine\Modulus\Contracts\ModuleSeederRunner;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Contracts\PackageDiscoverySyncer;
use InEngine\Modulus\Exceptions\MissingModuleException;
use InEngine\Modulus\Exceptions\ModuleSeederMissingException;
use InEngine\Modulus\Exceptions\SeedingConfirmationRequiredException;

/**
 * Orchestrates module registration, lifecycle, publish, migrations, seed, and toggles.
 *
 * Facade and Artisan commands should call this class only — not the store,
 * publisher, migrator, seeder runner, lifecycle runner, or discovery syncer directly.
 *
 * Step 6 pipeline (non-destructive disable / forget):
 * - enable: activating → migrate → state enabled → sync → activated
 * - disable: deactivating → state disabled → sync → deactivated
 * - forget / uninstall: disable if needed → uninstalling → remove state → sync → uninstalled
 * - register version bump while enabled: upgrading → migrate → upgraded
 * - publish: explicit {@see publish()} for install/update — owned by modulus-composer
 * - seed: explicit {@see seed()} with confirmation — never on enable/install
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
        ConfigRepository $config,
    ): self {
        $deny = $config->get('modulus.discovery.deny', ['inengine/modulus']);

        if (! is_array($deny)) {
            $deny = ['inengine/modulus'];
        }

        /** @var list<string> $denyList */
        $denyList = array_values(array_filter($deny, 'is_string'));

        return new self($store, $discovery, $installedPackages, $migrator, $publisher, $seeders, $lifecycle, $denyList);
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

            return;
        }

        if ($version !== null && $existing['version'] !== $version) {
            $wasEnabled = $existing['enabled'] === true;
            $existing['version'] = $version;
            $this->store->put($package, $existing);

            if ($wasEnabled) {
                $this->lifecycle->upgrading($package);
                $this->migrator->migrate($package);
                $this->lifecycle->upgraded($package);
            }

            $this->discovery->sync();
        }
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

        $this->lifecycle->activating($package);
        $this->migrator->migrate($package);
        $this->register($package);
        $this->store->setEnabled($package, true);
        $this->discovery->sync();
        $this->lifecycle->activated($package);
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

        $this->lifecycle->deactivating($package);
        $this->store->setEnabled($package, false);
        $this->discovery->sync();
        $this->lifecycle->deactivated($package);
    }

    /**
     * Drop a package from Modulus state (does not run composer remove).
     *
     * Non-destructive of module data. If the package is enabled, runs {@see disable()}
     * first. Composer remove belongs to inengine/modulus-composer.
     *
     * @throws \InvalidArgumentException
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

        if ($this->store->isEnabled($package)) {
            $this->disable($package);
        }

        $this->lifecycle->uninstalling($package);
        $this->store->remove($package);
        $this->discovery->sync();
        $this->lifecycle->uninstalled($package);
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

    public function isDenied(string $package): bool
    {
        return in_array($package, $this->deny, true);
    }
}
