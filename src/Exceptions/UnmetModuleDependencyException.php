<?php

namespace InEngine\Modulus\Exceptions;

use RuntimeException;

/**
 * Enable blocked because Composer requires are missing or Modulus deps are disabled.
 */
class UnmetModuleDependencyException extends RuntimeException
{
    /**
     * @param  list<string>  $missingPackages  Required packages that are not installed
     * @param  list<string>  $disabledModules  Required Modulus modules that are installed but not enabled
     */
    public function __construct(
        public readonly string $package,
        public readonly array $missingPackages = [],
        public readonly array $disabledModules = [],
    ) {
        $parts = [];

        if ($missingPackages !== []) {
            $parts[] = 'missing Composer packages ['.implode(', ', $missingPackages).']';
        }

        if ($disabledModules !== []) {
            $parts[] = 'disabled Modulus modules ['.implode(', ', $disabledModules).'] (enable them first)';
        }

        $detail = $parts === [] ? 'unmet dependencies' : implode('; ', $parts);

        parent::__construct(
            "Cannot enable [{$package}]: {$detail}."
        );
    }
}
