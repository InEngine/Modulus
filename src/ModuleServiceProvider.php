<?php

namespace InEngine\Modulus;

use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Base provider for Modulus-compatible feature modules (defense-in-depth handshake).
 *
 * Flow when Modulus is present:
 * 1. {@see register()} / Spatie config registration always runs (provider was loaded).
 * 2. {@see packageRegistered()} registers the Composer package with {@see ModuleRepository}
 *    (new packages default to disabled).
 * 3. {@see boot()} runs Spatie boot (routes, views, migrations publish hooks, etc.) **only**
 *    when Modulus reports the package enabled.
 *
 * When Modulus is absent, the module boots fully (standalone package use).
 *
 * Primary load gating remains PackageManifest filtering; this handshake covers stale
 * discovery cache or a manually registered provider.
 */
abstract class ModuleServiceProvider extends PackageServiceProvider
{
    /**
     * Composer package name, e.g. "inengine/alert".
     */
    abstract protected function modulusPackageName(): string;

    /**
     * Optional Composer version string passed to Modulus on register.
     */
    protected function modulusPackageVersion(): ?string
    {
        return null;
    }

    /**
     * Spatie hook: register with Modulus after package config is wired.
     */
    public function packageRegistered(): void
    {
        $this->registerWithModulus();
    }

    /**
     * {@inheritdoc}
     *
     * Skips all Spatie boot steps when Modulus says this module is disabled.
     */
    public function boot()
    {
        if (! $this->shouldBootModule()) {
            return $this;
        }

        return parent::boot();
    }

    /**
     * Whether Modulus runtime is available in the container.
     */
    public function modulusIsPresent(): bool
    {
        return $this->app->bound(ModuleRepository::class);
    }

    /**
     * Whether this module should run package boot logic.
     *
     * Standalone (no Modulus): always true. With Modulus: only when enabled.
     */
    public function shouldBootModule(): bool
    {
        if (! $this->modulusIsPresent()) {
            return true;
        }

        return $this->modulusRepository()->isEnabled(
            $this->modulusPackageName()
        );
    }

    /**
     * Whether Modulus currently reports this package as enabled.
     *
     * Returns false when Modulus is absent (callers should use {@see shouldBootModule()}
     * for boot decisions).
     */
    public function isEnabledInModulus(): bool
    {
        if (! $this->modulusIsPresent()) {
            return false;
        }

        return $this->modulusRepository()->isEnabled(
            $this->modulusPackageName()
        );
    }

    /**
     * Register this package with Modulus when the runtime is present.
     */
    protected function registerWithModulus(): void
    {
        if (! $this->modulusIsPresent()) {
            return;
        }

        $this->modulusRepository()->register(
            $this->modulusPackageName(),
            $this->modulusPackageVersion(),
        );
    }

    protected function modulusRepository(): ModuleRepository
    {
        return $this->app->make(ModuleRepository::class);
    }
}
