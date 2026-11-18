<?php

namespace InEngine\Modulus\Events;

use InEngine\Modulus\DataTransferObjects\ModuleStateData;

/**
 * A module finished enable (activating → migrate → state → sync → activated).
 *
 * Not dispatched for already-enabled re-migrate or deny-list register-only paths.
 */
final readonly class ModuleEnabled
{
    public function __construct(
        public ModuleStateData $module,
    ) {}
}
