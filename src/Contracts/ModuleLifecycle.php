<?php

namespace InEngine\Modulus\Contracts;

use InEngine\Modulus\ModuleServiceProvider;

/**
 * Optional hooks modules can override for activate / deactivate / uninstall / upgrade.
 *
 * Default implementations on {@see ModuleServiceProvider} are no-ops.
 * Disable and uninstall must stay non-destructive (no schema drops or data wipes).
 * Schema changes belong in migrations run on enable / upgrade only.
 */
interface ModuleLifecycle
{
    /**
     * Before the package is marked enabled and discovery is synced.
     */
    public function activating(string $package): void;

    /**
     * After the package is enabled, migrated, and discovery is synced.
     */
    public function activated(string $package): void;

    /**
     * Before the package is marked disabled. Temp cleanup only — keep module data.
     */
    public function deactivating(string $package): void;

    /**
     * After the package is disabled and discovery is synced.
     */
    public function deactivated(string $package): void;

    /**
     * Before the package is dropped from Modulus state (forget / uninstall-from-state).
     *
     * Non-destructive: do not drop tables or wipe storage. Composer remove is owned
     * by inengine/modulus-composer, not this hook.
     */
    public function uninstalling(string $package): void;

    /**
     * After the package has been removed from Modulus state and discovery synced.
     */
    public function uninstalled(string $package): void;

    /**
     * Before migrations run for an already-enabled package whose version changed.
     */
    public function upgrading(string $package): void;

    /**
     * After migrations run for a version bump on an enabled package.
     */
    public function upgraded(string $package): void;
}
