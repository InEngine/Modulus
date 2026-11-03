<?php

namespace InEngine\Modulus\Discovery;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\PackageManifest;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Contracts\PackageDiscoverySyncer;

/**
 * Drops the bound {@see PackageManifest} so a fresh filtered instance is resolved next.
 *
 * Filtering is read-time against {@see ModulusStateStore};
 * forgetting the singleton is enough for in-process toggles without rewriting packages.php.
 */
class FilteringPackageDiscoverySyncer implements PackageDiscoverySyncer
{
    public function __construct(
        protected Application $app,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function sync(): void
    {
        if ($this->app->bound(PackageManifest::class)) {
            $this->app->forgetInstance(PackageManifest::class);
        }
    }
}
