<?php

namespace InEngine\Modulus\Contracts;

/**
 * Reports Composer install presence and package metadata Modulus needs at runtime.
 *
 * Used for missing-module checks, migration path resolution, and lifecycle
 * provider discovery without booting disabled modules.
 */
interface InstalledPackageLocator
{
    /**
     * Whether the given Composer package name is installed.
     */
    public function isInstalled(string $package): bool;

    /**
     * Absolute filesystem path to the installed package, or null when unknown.
     */
    public function installPath(string $package): ?string;

    /**
     * Laravel provider class names from the package's composer.json
     * `extra.laravel.providers` list.
     *
     * @return list<class-string>
     */
    public function laravelProviderClasses(string $package): array;

    /**
     * Non-platform package names from the package's composer.json `require`.
     *
     * Skips php / ext-* / lib-* / hhvm / composer-* platform constraints.
     * Does not include require-dev.
     *
     * @return list<string>
     */
    public function composerRequires(string $package): array;

    /**
     * All installed Composer package names known to the runtime.
     *
     * @return list<string>
     */
    public function installedPackageNames(): array;

    /**
     * Pretty or raw installed version string, or null when unknown.
     */
    public function version(string $package): ?string;

    /**
     * Host root composer.json `require` constraint for a package, if present.
     */
    public function rootConstraint(string $package): ?string;
}
