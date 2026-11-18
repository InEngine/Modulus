<?php

namespace InEngine\Modulus\Contracts;

/**
 * Runs pending migrations for one Composer module package.
 *
 * Invoked on enable and on version-bump upgrade while enabled. Never on
 * disable or forget — those operations are non-destructive of schema/data.
 */
interface ModuleMigrator
{
    /**
     * Apply pending migrations for the package when a migrations directory exists.
     *
     * No-ops when the package has no install path or no migrations folder.
     */
    public function migrate(string $package): void;
}
