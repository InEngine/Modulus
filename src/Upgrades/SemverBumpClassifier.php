<?php

namespace InEngine\Modulus\Upgrades;

use Composer\Semver\VersionParser;
use InEngine\Modulus\DataTransferObjects\AvailableModuleUpdate;

/**
 * Classifies installed → latest as patch / minor / major using composer/semver.
 */
final class SemverBumpClassifier
{
    public function __construct(
        protected VersionParser $parser = new VersionParser,
    ) {}

    public function classify(string $from, string $to): string
    {
        try {
            $fromParts = $this->numericParts($this->parser->normalize($from));
            $toParts = $this->numericParts($this->parser->normalize($to));
        } catch (\UnexpectedValueException) {
            return AvailableModuleUpdate::BUMP_UNKNOWN;
        }

        if ($toParts[0] !== $fromParts[0]) {
            return AvailableModuleUpdate::BUMP_MAJOR;
        }

        if ($toParts[1] !== $fromParts[1]) {
            return AvailableModuleUpdate::BUMP_MINOR;
        }

        if ($toParts[2] !== $fromParts[2]) {
            return AvailableModuleUpdate::BUMP_PATCH;
        }

        return AvailableModuleUpdate::BUMP_UNKNOWN;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected function numericParts(string $normalized): array
    {
        $core = explode('+', $normalized)[0];
        $core = explode('-', $core)[0];
        $parts = array_map('intval', explode('.', $core));

        return [
            $parts[0],
            $parts[1] ?? 0,
            $parts[2] ?? 0,
        ];
    }
}
