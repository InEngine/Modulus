<?php

namespace InEngine\Modulus\DataTransferObjects;

/**
 * One diagnostic row for `modulus:doctor` and modulus-ui notification feeds.
 */
final readonly class DoctorFinding
{
    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_INFO = 'info';

    /**
     * @param  list<string>  $suggestions
     */
    public function __construct(
        public string $code,
        public string $severity,
        public string $message,
        public ?string $package = null,
        public array $suggestions = [],
    ) {}

    public function isError(): bool
    {
        return $this->severity === self::SEVERITY_ERROR;
    }
}
