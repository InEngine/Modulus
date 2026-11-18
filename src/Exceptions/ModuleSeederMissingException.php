<?php

namespace InEngine\Modulus\Exceptions;

use InEngine\Modulus\Contracts\ModuleSeeder;
use InEngine\Modulus\ModuleServiceProvider;
use RuntimeException;

/**
 * The module did not expose a valid {@see ModuleSeeder}.
 *
 * Every skeleton-based module should return a seeder class from
 * {@see ModuleServiceProvider::modulusSeederClass()}.
 */
class ModuleSeederMissingException extends RuntimeException
{
    public function __construct(public readonly string $package)
    {
        parent::__construct(
            "Module [{$package}] has no Modulus ModuleSeeder. ".
            'Override modulusSeederClass() on the ModuleServiceProvider '.
            '(skeleton ships Database\\Seeders\\ModuleSeeder).'
        );
    }
}
