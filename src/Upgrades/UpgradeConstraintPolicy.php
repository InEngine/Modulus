<?php

namespace InEngine\Modulus\Upgrades;

/**
 * Interprets modulus.upgrades.default_constraint for composer + doctor/UI.
 *
 * patch (default): host should require ~x.y.z so Composer lock only moves patches
 * until an operator approves a minor/major bump (UI/CLI → modulus-composer).
 */
final class UpgradeConstraintPolicy
{
    public const PATCH = 'patch';

    public const MINOR = 'minor';

    public const MAJOR = 'major';

    public function __construct(
        protected string $policy = self::PATCH,
    ) {
        if (! in_array($this->policy, [self::PATCH, self::MINOR, self::MAJOR], true)) {
            $this->policy = self::PATCH;
        }
    }

    public static function fromConfig(mixed $value): self
    {
        return new self(is_string($value) && $value !== '' ? $value : self::PATCH);
    }

    public function policy(): string
    {
        return $this->policy;
    }

    /**
     * Suggested Composer constraint for an installed version under this policy.
     */
    public function suggestConstraint(string $version): string
    {
        $normalized = ltrim($version, 'v');

        return match ($this->policy) {
            self::MINOR => '^'.$normalized,
            self::MAJOR => '*',
            default => '~'.$normalized,
        };
    }

    /**
     * Whether a root composer.json constraint is looser than the default policy.
     */
    public function constraintAllowsUnapprovedBumps(?string $constraint): bool
    {
        if ($constraint === null || $constraint === '') {
            return false;
        }

        $constraint = trim($constraint);

        if ($constraint === '*' || str_starts_with($constraint, 'dev-')) {
            return true;
        }

        if ($this->policy === self::PATCH) {
            // ^x.y allows minor bumps; >=x / * also too loose for patch-only policy.
            return str_starts_with($constraint, '^')
                || str_starts_with($constraint, '>=')
                || str_contains($constraint, '||');
        }

        if ($this->policy === self::MINOR) {
            return $constraint === '*';
        }

        return false;
    }
}
