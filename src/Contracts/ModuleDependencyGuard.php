<?php

namespace InEngine\Modulus\Contracts;

use InEngine\Modulus\Exceptions\ModuleHasDependentsException;
use InEngine\Modulus\Exceptions\UnmetModuleDependencyException;

/**
 * Enforces Composer-based module dependency rules (Step 7).
 *
 * Modulus modules among composer require must be installed and enabled.
 * Non-Modulus packages must be installed only (the requiring module owns
 * any vendor:publish / migrate bootstrap for those packages).
 */
interface ModuleDependencyGuard
{
    /**
     * @throws UnmetModuleDependencyException
     */
    public function assertCanEnable(string $package): void;

    /**
     * @throws ModuleHasDependentsException
     */
    public function assertCanDisable(string $package): void;
}
