<?php

namespace InEngine\Modulus\Events;

use InEngine\Modulus\DataTransferObjects\ModuleStateData;

/**
 * A module finished disable (non-destructive).
 */
final readonly class ModuleDisabled
{
    public function __construct(
        public ModuleStateData $module,
    ) {}
}
