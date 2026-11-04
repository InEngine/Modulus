<?php

namespace InEngine\Modulus\Contracts;

/**
 * Reports whether a Composer package is present in the installed set.
 */
interface InstalledPackageLocator
{
    /**
     * Whether the given Composer package name is installed.
     */
    public function isInstalled(string $package): bool;
}
