<?php

namespace InEngine\Modulus\DataTransferObjects;

/**
 * A newer Composer version is available for a Modulus-tracked module.
 *
 * Consumed by `modulus:outdated`, `modulus:doctor`, and modulus-ui notices.
 */
final readonly class AvailableModuleUpdate
{
    public const BUMP_PATCH = 'patch';

    public const BUMP_MINOR = 'minor';

    public const BUMP_MAJOR = 'major';

    public const BUMP_UNKNOWN = 'unknown';

    public function __construct(
        public string $package,
        public string $installedVersion,
        public string $latestVersion,
        public string $bump,
        public bool $withinConstraint,
        public bool $autoEligible,
        public ?string $rootConstraint = null,
    ) {}

    public function isNonBreaking(): bool
    {
        return $this->bump === self::BUMP_PATCH;
    }
}
