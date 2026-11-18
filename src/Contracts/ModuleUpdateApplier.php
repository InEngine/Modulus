<?php

namespace InEngine\Modulus\Contracts;

use InEngine\Modulus\DataTransferObjects\AvailableModuleUpdate;

/**
 * Applies Composer updates for modules (implemented by modulus-composer).
 *
 * Modulus core only notifies and optionally delegates here — it does not shell
 * Composer itself unless a binding is provided.
 */
interface ModuleUpdateApplier
{
    /**
     * Apply the given updates (typically patch/auto-eligible only).
     *
     * @param  list<AvailableModuleUpdate>  $updates
     * @return list<string> Package names successfully updated in the lock/vendor
     */
    public function apply(array $updates): array;
}
