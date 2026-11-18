<?php

namespace InEngine\Modulus\Updates;

use InEngine\Modulus\Contracts\ModuleUpdateApplier;

/**
 * Default applier: does not run Composer. modulus-composer replaces this binding.
 */
final class NullModuleUpdateApplier implements ModuleUpdateApplier
{
    /**
     * {@inheritdoc}
     */
    public function apply(array $updates): array
    {
        return [];
    }
}
