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
        $root = $this->installPath($package);

        if ($root === null) {
            return [];
        }

        $composerFile = $root.DIRECTORY_SEPARATOR.'composer.json';

        if (! is_file($composerFile)) {
            return [];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode((string) file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
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
}
