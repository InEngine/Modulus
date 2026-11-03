<?php

namespace InEngine\Modulus;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Contracts\PackageDiscoverySyncer;

/**
 * Orchestrates module registration and enable/disable against the state store.
 *
 * Facade and Artisan commands should call this class only — not the store or
 * discovery syncer directly.
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
        protected array $deny = ['inengine/modulus'],
    ) {}

    /**
     * Build from container config (deny list).
     */
    public static function fromConfig(
        ModulusStateStore $store,
        PackageDiscoverySyncer $discovery,
        ConfigRepository $config,
    ): self {
        $deny = $config->get('modulus.discovery.deny', ['inengine/modulus']);

        if (! is_array($deny)) {
            $deny = ['inengine/modulus'];
        }

        /** @var list<string> $denyList */
        $denyList = array_values(array_filter($deny, 'is_string'));

        return new self($store, $discovery, $denyList);
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
            $existing['version'] = $version;
            $this->store->put($package, $existing);
            $this->discovery->sync();
        }
    }

    /**
     * Enable a tracked package (registers first when unknown).
     */
    public function enable(string $package): void
    {
        if ($this->isDenied($package)) {
            $this->register($package);

            return;
        }

        $this->register($package);
        $this->store->setEnabled($package, true);
        $this->discovery->sync();
    }

    /**
     * Disable a tracked package. Denied packages cannot be disabled.
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

        $this->register($package);
        $this->store->setEnabled($package, false);
        $this->discovery->sync();
    }

    /**
     * Drop a package from Modulus state (does not run composer remove).
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

        $this->store->remove($package);
        $this->discovery->sync();
    }

    public function isDenied(string $package): bool
    {
        return in_array($package, $this->deny, true);
    }
}
