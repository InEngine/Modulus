<?php

namespace InEngine\Modulus\Events;

/**
 * Module resources were published into the host (install / Composer update path).
 */
final readonly class ModulePublished
{
    public function __construct(
        public string $package,
    ) {}
}
