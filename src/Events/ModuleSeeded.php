<?php

namespace InEngine\Modulus\Events;

/**
 * A module seeder finished after explicit confirmed {@see \InEngine\Modulus\ModuleRepository::seed()}.
 */
final readonly class ModuleSeeded
{
    public function __construct(
        public string $package,
    ) {}
}
