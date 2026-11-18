<?php

namespace InEngine\Modulus\Events;

use InEngine\Modulus\DataTransferObjects\ModuleStateData;

/**
 * An already-enabled module reported a new Composer version (upgrade + migrate).
 */
final readonly class ModuleUpgraded
{
    public function __construct(
        public ModuleStateData $module,
        public ?string $previousVersion,
    ) {}
}
