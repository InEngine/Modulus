<?php

namespace InEngine\Modulus\Events;

/**
 * A module (or doctor/upgrade pass) reported items that need operator attention.
 *
 * modulus-ui should notify admins and optionally deep-link to a checklist screen.
 *
 * @phpstan-type AttentionList list<string>
 */
final readonly class ModuleManualAttentionRequired
{
    /**
     * @param  AttentionList  $items
     */
    public function __construct(
        public string $package,
        public array $items,
        public ?string $version = null,
    ) {}
}
