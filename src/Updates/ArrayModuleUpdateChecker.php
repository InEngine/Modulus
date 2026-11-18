<?php

namespace InEngine\Modulus\Updates;

use InEngine\Modulus\Contracts\ModuleUpdateChecker;
use InEngine\Modulus\DataTransferObjects\AvailableModuleUpdate;

/**
 * Test / UI fixture checker that returns a fixed update list.
 */
final class ArrayModuleUpdateChecker implements ModuleUpdateChecker
{
    /**
     * @param  list<AvailableModuleUpdate>  $updates
     */
    public function __construct(
        protected array $updates = [],
    ) {}

    /**
     * {@inheritdoc}
     */
    public function availableUpdates(): array
    {
        return $this->updates;
    }
}
