<?php

namespace InEngine\Modulus\Dependencies;

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

    protected function isEffectivelyEnabled(string $package): bool
    {
        if (in_array($package, $this->deny, true)) {
            return true;
        }

        return $this->store->isEnabled($package);
    }
}
