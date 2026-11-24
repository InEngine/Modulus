<?php

namespace InEngine\Modulus\Support;

use Illuminate\Contracts\Foundation\Application;
use InEngine\Modulus\Capabilities\CapabilityRegistry;
use InEngine\Modulus\ModuleRepository;

/**
 * Generic readiness gate for companions that extend another module at runtime
 * (macros, resolveRelationUsing, optional binds).
 *
 * Declare what the hub provides (capability tokens), which package must be
 * enabled, and which config keys must exist — then run the extension callback
 * only when those expectations are met.
 */
final class ModuleExtensionGate
{
    public function __construct(
        protected Application $app,
    ) {}

    /**
     * @param  list<string>  $tokens  Capability tokens that must have an enabled provider
     * @param  list<string>  $packages  Composer packages that must be Modulus-enabled
     * @param  list<string>  $configKeys  Dot keys that must exist (e.g. user.model)
     */
    public function isReady(
        array $tokens = [],
        array $packages = [],
        array $configKeys = [],
    ): bool {
        if (! $this->modulusIsPresent()) {
            return $this->configKeysPresent($configKeys);
        }

        if (! $this->tokensProvided($tokens)) {
            return false;
        }

        if (! $this->packagesEnabled($packages)) {
            return false;
        }

        return $this->configKeysPresent($configKeys);
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<string>  $packages
     * @param  list<string>  $configKeys
     * @param  callable(): void  $callback
     */
    public function whenReady(
        callable $callback,
        array $tokens = [],
        array $packages = [],
        array $configKeys = [],
    ): bool {
        if (! $this->isReady($tokens, $packages, $configKeys)) {
            return false;
        }

        $callback();

        return true;
    }

    /**
     * @param  list<string>  $tokens
     */
    public function tokensProvided(array $tokens, bool $enabledOnly = true): bool
    {
        if ($tokens === []) {
            return true;
        }

        if (! $this->app->bound(CapabilityRegistry::class)) {
            return false;
        }

        /** @var CapabilityRegistry $registry */
        $registry = $this->app->make(CapabilityRegistry::class);

        foreach ($tokens as $token) {
            if (! $registry->hasProvider($token, $enabledOnly)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $packages
     */
    public function packagesEnabled(array $packages): bool
    {
        if ($packages === []) {
            return true;
        }

        if (! $this->app->bound(ModuleRepository::class)) {
            return false;
        }

        /** @var ModuleRepository $modules */
        $modules = $this->app->make(ModuleRepository::class);

        foreach ($packages as $package) {
            if (! $modules->isEnabled($package)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $configKeys
     */
    public function configKeysPresent(array $configKeys): bool
    {
        foreach ($configKeys as $key) {
            if (! config()->has($key)) {
                return false;
            }

            $value = config($key);

            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }

    protected function modulusIsPresent(): bool
    {
        return $this->app->bound(CapabilityRegistry::class)
            && $this->app->bound(ModuleRepository::class);
    }
}
