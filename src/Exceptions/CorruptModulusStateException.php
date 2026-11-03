<?php

namespace InEngine\Modulus\Exceptions;

use RuntimeException;

/**
 * Thrown when the Modulus state file exists but cannot be parsed as valid JSON state.
 */
class CorruptModulusStateException extends RuntimeException
{
    public static function invalidJson(string $path, string $jsonError): self
    {
        return new self("Modulus state file at [{$path}] contains invalid JSON: {$jsonError}");
    }

    public static function invalidShape(string $path): self
    {
        return new self("Modulus state file at [{$path}] must decode to a JSON object keyed by package name.");
    }
}
