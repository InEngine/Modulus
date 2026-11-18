<?php

namespace InEngine\Modulus\Events;

/**
 * A module was removed from Modulus state (forget / uninstall-from-state).
 *
 * Composer remove is owned by modulus-composer; this event is state-only.
 */
final readonly class ModuleForgotten
{
    public function __construct(
        public string $package,
    ) {}
}
