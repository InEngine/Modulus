<?php

namespace InEngine\Modulus\Contracts;

/**
 * Publishes module resources into the host app (config, public assets, optional UI).
 *
 * Owned by the **install / Composer update** path (`ModuleRepository::publish()`),
 * which `inengine/modulus-composer` should call after require/update — not by
 * enable/disable. Works from the Composer install path without booting the
 * module provider.
 *
 * Policy defaults:
 * - config: publish missing files for host edits (Spatie also mergeConfigFrom vendor)
 * - assets: publish `resources/dist` into `public/vendor/{name}` (required for HTTP UI)
 * - views / lang / inertia: opt-in overrides only; packages load these from vendor by default
 */
interface ModulePublisher
{
    /**
     * Publish configured resource kinds for the package.
     *
     * No-ops when the package has no install path or nothing to publish.
     */
    public function publish(string $package): void;
}
