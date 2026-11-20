<?php

namespace InEngine\Modulus\Capabilities;

use Illuminate\Contracts\Foundation\Application;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\ModuleServiceProvider;

/**
 * Resolve capability descriptor payloads for installed packages without Laravel-registering them.
 *
 * Instantiates the first {@see ModuleServiceProvider} from Composer `extra.laravel.providers`
 * (same pattern as lifecycle hooks) and reads {@see ModuleServiceProvider::modulusCapabilities()}.
 *
 * @phpstan-import-type ModuleCapabilitiesShape from ModuleCapabilities
 */
class ModuleCapabilityLocator
{
    public function __construct(
        protected Application $app,
        protected InstalledPackageLocator $packages,
    ) {}

    /**
     * @return ModuleCapabilitiesShape|null Null when the package has no Modulus provider.
     */
    public function locate(string $package): ?array
    {
        foreach ($this->packages->laravelProviderClasses($package) as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, ModuleServiceProvider::class)) {
                continue;
            }

            /** @var ModuleServiceProvider $provider */
            $provider = new $class($this->app);

            return $provider->modulusCapabilities();
        }

        return null;
    }
}
