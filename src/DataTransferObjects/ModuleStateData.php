<?php

namespace InEngine\Modulus\DataTransferObjects;

/**
 * Typed snapshot of a module's Modulus state for events and cross-boundary payloads.
 *
 * @phpstan-type ModuleState array{enabled: bool, version: string|null, capabilities?: array<string, mixed>}
 */
final readonly class ModuleStateData
{
    public function __construct(
        public string $package,
        public bool $enabled,
        public ?string $version = null,
    ) {}

    /**
     * @param  ModuleState  $state
     */
    public static function fromStore(string $package, array $state): self
    {
        return new self(
            package: $package,
            enabled: $state['enabled'] === true,
            version: $state['version'] ?? null,
        );
    }
}
