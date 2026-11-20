<?php

namespace InEngine\Modulus\DataTransferObjects;

/**
 * Structured doctor output for CLI and modulus-ui.
 *
 * @phpstan-type FindingList list<DoctorFinding>
 */
final readonly class DoctorReport
{
    /**
     * @param  FindingList  $findings
     */
    public function __construct(
        public array $findings,
        public bool $stateHealthy,
        public string $phpVersion,
        public string $defaultConstraintPolicy,
    ) {}

    /** Whether any finding is an error (see {@see DoctorFinding::isError()}). */
    public function hasErrors(): bool
    {
        foreach ($this->findings as $finding) {
            if ($finding->isError()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return FindingList
     */
    public function errors(): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (DoctorFinding $finding): bool => $finding->isError(),
        ));
    }
}
