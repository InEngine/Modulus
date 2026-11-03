<?php

namespace InEngine\Modulus\State;

use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Exceptions\CorruptModulusStateException;

/**
 * File-backed {@see ModulusStateStore} using JSON and an atomic temp-write + rename.
 *
 * @phpstan-import-type ModuleState from ModulusStateStore
 */
class FileModulusStateStore implements ModulusStateStore
{
    public function __construct(
        protected string $path,
    ) {}

    /**
     * {@inheritdoc}
     *
     * @return array<string, ModuleState>
     */
    public function all(): array
    {
        return $this->read();
    }

    /**
     * {@inheritdoc}
     *
     * @return ModuleState|null
     */
    public function get(string $package): ?array
    {
        $modules = $this->read();

        return $modules[$package] ?? null;
    }

    /**
     * {@inheritdoc}
     *
     * @param  ModuleState  $data
     */
    public function put(string $package, array $data): void
    {
        $modules = $this->read();
        $modules[$package] = $this->normalizeEntry($data);
        $this->write($modules);
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(string $package): bool
    {
        $entry = $this->get($package);

        return $entry !== null && $entry['enabled'] === true;
    }

    /**
     * {@inheritdoc}
     */
    public function setEnabled(string $package, bool $enabled): void
    {
        $entry = $this->get($package) ?? [
            'enabled' => false,
            'version' => null,
        ];

        $entry['enabled'] = $enabled;

        $this->put($package, $entry);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $package): void
    {
        $modules = $this->read();

        if (! array_key_exists($package, $modules)) {
            return;
        }

        unset($modules[$package]);
        $this->write($modules);
    }

    /**
     * @return array<string, ModuleState>
     */
    protected function read(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw CorruptModulusStateException::invalidJson($this->path, $exception->getMessage());
        }

        // json_decode('{}', true) yields [] in PHP, and array_is_list([]) is true — so do not
        // reject empty arrays. Reject non-object JSON by requiring string package keys below.
        if (! is_array($decoded)) {
            throw CorruptModulusStateException::invalidShape($this->path);
        }

        $modules = [];

        foreach ($decoded as $package => $entry) {
            if (! is_string($package) || $package === '' || ! is_array($entry)) {
                throw CorruptModulusStateException::invalidShape($this->path);
            }

            $modules[$package] = $this->normalizeEntry($entry);
        }

        return $modules;
    }

    /**
     * Persist the full module map with a temp file + atomic rename.
     *
     * @param  array<string, ModuleState>  $modules
     */
    protected function write(array $modules): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create Modulus state directory [{$directory}].");
        }

        // JSON_FORCE_OBJECT keeps an empty map as `{}` (not `[]`), which read() rejects as invalid shape.
        $payload = json_encode(
            $modules,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT
        );

        $tempPath = $this->path.'.'.uniqid('tmp', true);

        $handle = fopen($tempPath, 'wb');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open temporary Modulus state file [{$tempPath}] for writing.");
        }

        try {
            if (fwrite($handle, $payload) === false) {
                throw new \RuntimeException("Unable to write temporary Modulus state file [{$tempPath}].");
            }

            fflush($handle);

            if (function_exists('fsync')) {
                fsync($handle);
            }
        } finally {
            fclose($handle);
        }

        // On Windows, rename() cannot overwrite an existing destination.
        if (DIRECTORY_SEPARATOR === '\\' && is_file($this->path) && ! @unlink($this->path)) {
            @unlink($tempPath);

            throw new \RuntimeException("Unable to replace existing Modulus state file [{$this->path}].");
        }

        if (! rename($tempPath, $this->path)) {
            @unlink($tempPath);

            throw new \RuntimeException("Unable to atomically replace Modulus state file [{$this->path}].");
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return ModuleState
     */
    protected function normalizeEntry(array $entry): array
    {
        $version = $entry['version'] ?? null;

        if ($version !== null && ! is_string($version)) {
            $version = null;
        }

        return [
            'enabled' => (bool) ($entry['enabled'] ?? false),
            'version' => $version,
        ];
    }
}
