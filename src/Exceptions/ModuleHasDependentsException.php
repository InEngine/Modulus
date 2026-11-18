<?php

namespace InEngine\Modulus\Exceptions;

use RuntimeException;

/**
 * Disable / forget blocked because enabled modules still require this package.
 */
class ModuleHasDependentsException extends RuntimeException
{
    /**
     * @param  list<string>  $dependents  Enabled Modulus modules that require $package
     */
    public function __construct(
        public readonly string $package,
        public readonly array $dependents,
    ) {
        $list = implode(', ', $dependents);

        parent::__construct(
            "Cannot disable or forget [{$package}] while enabled dependents require it: [{$list}]."
        );
    }
}
