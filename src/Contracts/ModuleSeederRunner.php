<?php

namespace InEngine\Modulus\Contracts;

use InEngine\Modulus\Exceptions\ModuleSeederMissingException;

/**
 * Resolves a package's {@see ModuleSeeder} without Laravel-registering its provider.
 */
interface ModuleSeederRunner
{
    /**
     * @throws ModuleSeederMissingException
     */
    public function resolve(string $package): ModuleSeeder;
}
