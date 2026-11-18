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
     * {@inheritdoc}
     *
     * @return list<string>
     */
    public function installedPackageNames(): array
    {
        if (! class_exists(InstalledVersions::class)) {
            return [];
        }

        $names = [];

        foreach (InstalledVersions::getInstalledPackages() as $name) {
            if ($name !== '' && str_contains($name, '/')) {
                $names[] = $name;
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * {@inheritdoc}
     */
    public function version(string $package): ?string
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled($package)) {
            return null;
        }

        $pretty = InstalledVersions::getPrettyVersion($package);

        if (is_string($pretty) && $pretty !== '') {
            return $pretty;
        }

        $version = InstalledVersions::getVersion($package);

        return is_string($version) && $version !== '' ? $version : null;
    }

    /**
     * {@inheritdoc}
     */
    public function rootConstraint(string $package): ?string
    {
        $rootPath = $this->rootComposerPath();

        if ($rootPath === null) {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode((string) file_get_contents($rootPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $require = $decoded['require'][$package] ?? null;

        return is_string($require) && $require !== '' ? $require : null;
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

    private function rootComposerPath(): ?string
    {
        if (! class_exists(InstalledVersions::class)) {
            return null;
        }

        $root = InstalledVersions::getRootPackage();
        $installPath = $root['install_path'];

        if ($installPath === '') {
            return null;
        }

        $composerFile = rtrim($installPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'composer.json';

        return is_file($composerFile) ? $composerFile : null;
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
