<?php

namespace InEngine\Modulus\Events;

use InEngine\Modulus\ModuleRepository;

/**
 * A module seeder finished after explicit confirmed {@see ModuleRepository::seed()}.
 */
final readonly class ModuleSeeded
{
    public function __construct(
        public string $package,
    ) {}
}
