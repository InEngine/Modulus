<?php

namespace InEngine\Modulus\Events;

use InEngine\Modulus\DataTransferObjects\AvailableModuleUpdate;

/**
 * One or more Modulus modules have Composer updates available.
 *
 * Fired by `modulus:outdated` / `modulus:update` (and doctor when configured)
 * so modulus-ui and CLI operators can notify admins.
 *
 * @phpstan-type UpdateList list<AvailableModuleUpdate>
 */
final readonly class ModuleUpdateAvailable
{
    /**
     * @param  UpdateList  $updates
     */
    public function __construct(
        public array $updates,
    ) {}
}
