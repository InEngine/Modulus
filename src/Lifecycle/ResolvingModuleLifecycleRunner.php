<?php

namespace InEngine\Modulus\Lifecycle;

use Illuminate\Contracts\Foundation\Application;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleLifecycle;
use InEngine\Modulus\Contracts\ModuleLifecycleRunner;
use InEngine\Modulus\ModuleServiceProvider;

/**
 * Invokes lifecycle hooks on a module's {@see ModuleServiceProvider} when resolvable.
 *
 * Discovery does not require the provider to be Laravel-registered (disabled modules
 * are filtered out of PackageManifest). We read `extra.laravel.providers` from the
 * package's composer.json, instantiate the first Modulus-aware provider, and call
 * the hook. Plain Laravel packages and missing providers are no-ops.
 */
final class ResolvingModuleLifecycleRunner implements ModuleLifecycleRunner
{
    public function __construct(
        protected Application $app,
        protected InstalledPackageLocator $packages,
    ) {}

    public function activating(string $package): void
    {
        $this->invoke($package, 'activating');
    }

    public function activated(string $package): void
    {
        $this->invoke($package, 'activated');
    }

    public function deactivating(string $package): void
    {
        $this->invoke($package, 'deactivating');
    }

    public function deactivated(string $package): void
    {
        $this->invoke($package, 'deactivated');
    }

    public function uninstalling(string $package): void
    {
        $this->invoke($package, 'uninstalling');
    }

    public function uninstalled(string $package): void
    {
        $this->invoke($package, 'uninstalled');
    }

    public function upgrading(string $package): void
    {
        $this->invoke($package, 'upgrading');
    }

    public function upgraded(string $package): void
    {
        $this->invoke($package, 'upgraded');
    }

    /**
     * @param  non-empty-string  $method
     */
    protected function invoke(string $package, string $method): void
    {
        $lifecycle = $this->resolve($package);

        if ($lifecycle === null) {
            return;
        }

        $lifecycle->{$method}($package);
    }

    /**
     * Build a lifecycle handler without registering the provider with Laravel.
     */
    protected function resolve(string $package): ?ModuleLifecycle
    {
        foreach ($this->packages->laravelProviderClasses($package) as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, ModuleServiceProvider::class)) {
                continue;
            }

            /** @var ModuleServiceProvider $provider */
            $provider = new $class($this->app);

            return $provider;
        }

        return null;
    }
}
