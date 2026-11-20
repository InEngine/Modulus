<?php

namespace InEngine\Modulus\Contracts;

/**
 * Durable source of truth for which Composer module packages are enabled.
 *
 * The default implementation is a flat JSON file store. Hosts may bind any
 * other implementation via `config('modulus.store')` or the container.
 * Unknown packages are treated as disabled until registered.
 *
 * @phpstan-type CapabilityList list<string>
 * @phpstan-type DriverMap array<string, list<string>>
 * @phpstan-type ModuleCapabilitiesShape array{
 *     provides: CapabilityList,
 *     requires: CapabilityList,
 *     events: CapabilityList,
 *     peers: CapabilityList,
 *     drivers: DriverMap,
 *     binds: CapabilityList,
 *     label: string|null,
 *     description: string|null
 * }
 * @phpstan-type ModuleState array{enabled: bool, version: string|null, capabilities?: ModuleCapabilitiesShape}
 */
interface ModulusStateStore
{
    /**
     * Return every tracked module keyed by Composer package name.
     *
     * @return array<string, ModuleState>
     */
    public function all(): array;

    /**
     * Return one module's state, or null when the package is not tracked.
     *
     * @return ModuleState|null
     */
    public function get(string $package): ?array;

    /**
     * Replace or create the state entry for a Composer package.
     *
     * @param  ModuleState  $data
     */
    public function put(string $package, array $data): void;

    /**
     * Whether the package is tracked and enabled.
     *
     * Unknown packages return false.
     */
    public function isEnabled(string $package): bool;

    /**
     * Set the enabled flag for a package, creating a disabled entry if needed.
     */
    public function setEnabled(string $package, bool $enabled): void;

    /**
     * Drop a package from the store when present.
     */
    public function remove(string $package): void;
}
