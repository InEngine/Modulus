<?php

namespace InEngine\Modulus\Exceptions;

use RuntimeException;

/**
 * Thrown when the Modulus state file exists but cannot be parsed as valid JSON state.
 */
class CorruptModulusStateException extends RuntimeException
{
    /**
     * State file contents are not valid JSON.
     *
     * @param  string  $path  State file path.
     * @param  string  $jsonError  Message from the JSON decoder.
     */
    public static function invalidJson(string $path, string $jsonError): self
    {
        return new self("Modulus state file at [{$path}] contains invalid JSON: {$jsonError}");
    }

    /**
     * State file decoded, but not to a JSON object keyed by package name.
     *
     * @param  string  $path  State file path.
     */
    public static function invalidShape(string $path): self
    {
        return new self("Modulus state file at [{$path}] must decode to a JSON object keyed by package name.");
    }
}
