<?php

namespace InEngine\Modulus\Composer;

use Composer\InstalledVersions;
use InEngine\Modulus\Contracts\InstalledPackageLocator;

/**
 * Uses Composer's runtime API plus each package's composer.json for metadata.
 */
final class ComposerInstalledPackageLocator implements InstalledPackageLocator
{
    /**
     * {@inheritdoc}
     */
    public function isInstalled(string $package): bool
    {
        if (! class_exists(InstalledVersions::class)) {
            return true;
        }

        return InstalledVersions::isInstalled($package);
    }

    /**
     * {@inheritdoc}
     */
    public function installPath(string $package): ?string
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled($package)) {
            return null;
        }

        $path = InstalledVersions::getInstallPath($package);

        if (! is_string($path) || $path === '') {
            return null;
        }

        return $path;
    }

    /**
     * {@inheritdoc}
     *
     * @return list<class-string>
     */
    public function laravelProviderClasses(string $package): array
    {
        $decoded = $this->composerJson($package);

        if ($decoded === null) {
            return [];
        }

        $providers = $decoded['extra']['laravel']['providers'] ?? [];

        if (! is_array($providers)) {
            return [];
        }

        /** @var list<class-string> $classes */
        $classes = array_values(array_filter(
            $providers,
            static fn (mixed $provider): bool => is_string($provider) && $provider !== '',
        ));

        return $classes;
    }

    /**
     * {@inheritdoc}
     *
     * @return list<string>
     */
    public function composerRequires(string $package): array
    {
        $decoded = $this->composerJson($package);

        if ($decoded === null) {
            return [];
        }

        $require = $decoded['require'] ?? [];

        if (! is_array($require)) {
            return [];
        }

        $names = [];

        foreach (array_keys($require) as $name) {
            if (! is_string($name) || $name === '' || $this->isPlatformRequirement($name)) {
                continue;
            }

            $names[] = $name;
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function composerJson(string $package): ?array
    {
        $root = $this->installPath($package);

        if ($root === null) {
            return null;
        }

        $composerFile = $root.DIRECTORY_SEPARATOR.'composer.json';

        if (! is_file($composerFile)) {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode((string) file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function isPlatformRequirement(string $name): bool
    {
        return $name === 'php'
            || $name === 'hhvm'
            || str_starts_with($name, 'ext-')
            || str_starts_with($name, 'lib-')
            || str_starts_with($name, 'composer-');
    }
}
