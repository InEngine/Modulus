<?php

namespace InEngine\Modulus;

use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Base provider for Modulus-compatible feature modules (Step 1 scaffold).
 *
 * Step 4 hardens the full handshake; this base already:
 * - registers the Composer package name with {@see ModuleRepository} when Modulus is present
 * - skips {@see boot()} unless the module is enabled (or Modulus is absent → standalone)
 */
abstract class ModuleServiceProvider extends PackageServiceProvider
{
    /**
     * Composer package name, e.g. "inengine/alert".
     */
    abstract protected function modulusPackageName(): string;

    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        parent::register();

        $this->registerWithModulus();
    }

    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        if (! $this->shouldBootModule()) {
            return;
        }

        parent::boot();
    }

    /**
     * Whether this module should run package boot logic.
     *
     * Standalone (no Modulus): always true. With Modulus: only when enabled.
     */
    public function shouldBootModule(): bool
    {
        if (! $this->app->bound(ModuleRepository::class)) {
            return true;
        }

        return $this->app->make(ModuleRepository::class)->isEnabled(
            $this->modulusPackageName()
        );
    }

    /**
     * Register this package with Modulus when the runtime is present.
     */
    protected function registerWithModulus(): void
    {
        if (! $this->app->bound(ModuleRepository::class)) {
            return;
        }

        $this->app->make(ModuleRepository::class)->register(
            $this->modulusPackageName()
        );
    }
}
