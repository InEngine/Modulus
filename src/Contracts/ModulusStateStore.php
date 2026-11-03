<?php

namespace InEngine\Modulus\Contracts;

/**
 * Durable source of truth for which Composer module packages are enabled.
 *
 * Implementations must persist with an atomic write (temp file + rename).
 * Unknown packages are treated as disabled until registered.
 *
 * @phpstan-type ModuleState array{enabled: bool, version: string|null}
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
