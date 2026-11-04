<?php

namespace InEngine\Modulus\Exceptions;

use RuntimeException;

/**
 * A package is tracked in Modulus state but is not installed via Composer.
 */
class MissingModuleException extends RuntimeException
{
    public function __construct(
        public readonly string $package,
        public readonly bool $wasEnabled,
    ) {
        $status = $wasEnabled ? 'enabled' : 'disabled';

        parent::__construct(
            "Modulus {$status} module [{$package}] is tracked in state but is not installed via Composer."
        );
    }
}
