<?php

namespace InEngine\Modulus\Composer;

use Composer\InstalledVersions;
use InEngine\Modulus\Contracts\InstalledPackageLocator;

/**
 * Uses Composer's runtime API to detect installed packages.
 */
final class ComposerInstalledPackageLocator implements InstalledPackageLocator
{
    public function isInstalled(string $package): bool
    {
        if (! class_exists(InstalledVersions::class)) {
            return true;
        }

        return InstalledVersions::isInstalled($package);
    }
}
