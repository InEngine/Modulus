<?php

namespace InEngine\Modulus\Exceptions;

use InEngine\Modulus\ModuleRepository;
use RuntimeException;

/**
 * {@see ModuleRepository::seed()} was called without confirmation.
 *
 * Pass confirmed: true only after an operator (or Artisan prompt) agrees.
 * Future modulus:seed should prompt, then call seed with confirmation.
 */
class SeedingConfirmationRequiredException extends RuntimeException
{
    public function __construct(public readonly string $package)
    {
        parent::__construct(
            "Seeding module [{$package}] requires confirmation. ".
            'Call Modulus::seed($package, confirmed: true) only after an explicit operator OK '.
            '(Artisan modulus:seed will prompt). Seeders are never run on enable/install.'
        );
    }
}
