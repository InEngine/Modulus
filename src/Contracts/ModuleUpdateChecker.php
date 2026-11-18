<?php

namespace InEngine\Modulus\Contracts;

use InEngine\Modulus\DataTransferObjects\AvailableModuleUpdate;

/**
 * Discovers available Composer updates for Modulus modules (CLI + UI notices).
 */
interface ModuleUpdateChecker
{
    /**
     * @return list<AvailableModuleUpdate>
     */
    public function availableUpdates(): array;
}
