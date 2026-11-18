<?php

namespace InEngine\Modulus\Events;

use InEngine\Modulus\DataTransferObjects\ModuleStateData;

/**
 * A Composer package was first tracked in Modulus state (usually disabled).
 */
final readonly class ModuleRegistered
{
    public function __construct(
        public ModuleStateData $module,
    ) {}
}
