<?php

namespace InEngine\Modulus\Dependencies;

use InEngine\Modulus\Capabilities\ModuleCapabilities;
use InEngine\Modulus\Capabilities\ModuleCapabilityLocator;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleDependencyGuard;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Exceptions\ModuleHasDependentsException;
use InEngine\Modulus\Exceptions\UnmetModuleDependencyException;
use InEngine\Modulus\ModuleServiceProvider;

/**
 * Dependency checks from Composer require + Modulus enable state.
 *
 * Classification: a package is a Modulus module when it is tracked in state,
 * on the discovery deny list, or ships a ModuleServiceProvider. Everything
 * else is a plain Composer dependency (installed-only on enable).
 *
 * Library modules (kind: library) satisfy enable deps when installed — they
 * need not be toggled on (and are auto-enabled on register / enable of dependents).
 */
final class ComposerModuleDependencyGuard implements ModuleDependencyGuard
{
    /**
     * @param  list<string>  $deny
     */
    public function __construct(
        protected ModulusStateStore $store,
        protected InstalledPackageLocator $installedPackages,
        protected array $deny = ['inengine/modulus'],
        protected ?ModuleCapabilityLocator $capabilityLocator = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function assertCanEnable(string $package): void
    {
        $missing = [];
        $disabled = [];

        foreach ($this->installedPackages->composerRequires($package) as $dependency) {
            if (! $this->installedPackages->isInstalled($dependency)) {
                $missing[] = $dependency;

                continue;
            }

            if (! $this->isModulusModule($dependency)) {
                continue;
            }

            if ($this->isLibraryModule($dependency)) {
                continue;
            }

            if (! $this->isEffectivelyEnabled($dependency)) {
                $disabled[] = $dependency;
            }
        }

        if ($missing !== [] || $disabled !== []) {
            throw new UnmetModuleDependencyException($package, $missing, $disabled);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function assertCanDisable(string $package): void
    {
        $dependents = $this->enabledDependents($package);

        if ($dependents !== []) {
            throw new ModuleHasDependentsException($package, $dependents);
        }
    }

    /**
     * Enabled Modulus modules whose composer.json require includes $package.
     *
     * @return list<string>
     */
    public function enabledDependents(string $package): array
    {
        $dependents = [];

        foreach ($this->store->all() as $candidate => $state) {
            if ($candidate === $package || $state['enabled'] !== true) {
                continue;
            }

            if (! in_array($package, $this->installedPackages->composerRequires($candidate), true)) {
                continue;
            }

            $dependents[] = $candidate;
        }

        sort($dependents);

        return $dependents;
    }

    /** Whether a package is a Modulus module (tracked, denied, or ships a {@see ModuleServiceProvider}). */
    public function isModulusModule(string $package): bool
    {
        if (in_array($package, $this->deny, true)) {
            return true;
        }

        if ($this->store->get($package) !== null) {
            return true;
        }

        foreach ($this->installedPackages->laravelProviderClasses($package) as $class) {
            if (class_exists($class) && is_subclass_of($class, ModuleServiceProvider::class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this dependency is a library module (ports/tokens).
     *
     * Prefers persisted capabilities, then live descriptor via {@see ModuleCapabilityLocator}.
     */
    public function isLibraryModule(string $package): bool
    {
        $state = $this->store->get($package);

        if ($state !== null && array_key_exists('capabilities', $state)) {
            $capabilities = ModuleCapabilities::normalize($state['capabilities']);

            if ($capabilities['kind'] === ModuleCapabilities::KIND_LIBRARY) {
                return true;
            }
        }

        if ($this->capabilityLocator === null) {
            return false;
        }

        $located = $this->capabilityLocator->locate($package);

        if ($located === null) {
            return false;
        }

        return ModuleCapabilities::normalize($located)['kind'] === ModuleCapabilities::KIND_LIBRARY;
    }

    /** Whether a package counts as enabled; denied packages are always treated as enabled. */
    protected function isEffectivelyEnabled(string $package): bool
    {
        if (in_array($package, $this->deny, true)) {
            return true;
        }

        return $this->store->isEnabled($package);
    }
}
