<?php

namespace InEngine\Modulus;

use Composer\InstalledVersions;
use InEngine\Modulus\Contracts\ModuleLifecycle;
use InEngine\Modulus\Contracts\ModuleSeeder;
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
 *
 * Lifecycle (Step 6): override the {@see ModuleLifecycle} methods for activate /
 * deactivate / uninstall / upgrade side effects. Keep deactivate/uninstall
 * non-destructive; put schema changes in migrations (run on enable/upgrade).
 * Config/assets publish is {@see ModuleRepository::publish()} for install
 * (modulus-composer), not enable — Install ≠ enable.
 * Data seeders run only via {@see ModuleRepository::seed()} with confirmation —
 * never on enable. Skeleton modules must override {@see modulusSeederClass()}.
 *
 * Dependencies (Step 7): Composer `require` is checked by Modulus. Required
 * Modulus modules must already be enabled; plain Composer packages need only
 * be installed and still use normal Laravel discovery/publish. Modules may
 * optionally reach down from `activating()` to also ensure config, UI dist,
 * or migrations for those deps.
 */
abstract class ModuleServiceProvider extends PackageServiceProvider implements ModuleLifecycle
{
    /**
     * Composer package name, e.g. "inengine/alert".
     */
    abstract protected function modulusPackageName(): string;

    /**
     * Composer version string passed to Modulus on register.
     *
     * Defaults to the installed Composer pretty version so enabled modules
     * auto-apply upgrades (migrate + hooks) on first boot after `composer update`
     * when {@see config('modulus.upgrades.auto_apply_installed')} is true.
     * Override to pin a different reported version if needed.
     */
    protected function modulusPackageVersion(): ?string
    {
        if (config('modulus.upgrades.auto_apply_installed', true) === false) {
            return null;
        }

        $package = $this->modulusPackageName();

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
     * Post-upgrade / operator checklist items for modulus-ui and `modulus:doctor`.
     *
     * Return human-readable steps that need manual attention after an upgrade
     * (config review, feature flags, data backfill, etc.). Empty by default.
     *
     * @return list<string>
     */
    public function modulusManualAttentionItems(): array
    {
        return [];
    }

    /**
     * FQCN of this module's {@see ModuleSeeder}.
     *
     * Skeleton modules return Database\Seeders\ModuleSeeder. Null means
     * {@see ModuleRepository::seed()} will fail with ModuleSeederMissingException.
     *
     * @return class-string<ModuleSeeder>|null
     */
    public function modulusSeederClass(): ?string
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

    /** Resolve the {@see ModuleRepository} from the container. */
    protected function modulusRepository(): ModuleRepository
    {
        return $this->app->make(ModuleRepository::class);
    }

    /**
     * {@inheritdoc}
     *
     * Override for pre-enable work (defaults, cache warm). Migrations run separately.
     */
    public function activating(string $package): void
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function activated(string $package): void
    {
        //
    }

    /**
     * {@inheritdoc}
     *
     * Temp cleanup only — do not drop tables or wipe module storage.
     */
    public function deactivating(string $package): void
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function deactivated(string $package): void
    {
        //
    }

    /**
     * {@inheritdoc}
     *
     * Non-destructive: Composer remove is owned by modulus-composer, not this hook.
     */
    public function uninstalling(string $package): void
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function uninstalled(string $package): void
    {
        //
    }

    /**
     * {@inheritdoc}
     *
     * Override for pre-upgrade work. Pending migrations run after this hook.
     */
    public function upgrading(string $package): void
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function upgraded(string $package): void
    {
        //
    }
}
