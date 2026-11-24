<?php

namespace InEngine\Modulus\Capabilities;

use InEngine\Modulus\Contracts\ModulusStateStore;

/**
 * Query capability tokens and driver families persisted on the Modulus state store.
 *
 * @phpstan-import-type ModuleCapabilitiesShape from ModuleCapabilities
 */
final class CapabilityRegistry
{
    public function __construct(
        protected ModulusStateStore $store,
    ) {}

    /**
     * Packages that declare providing the given token.
     *
     * @return list<string>
     */
    public function providersOf(string $token, bool $enabledOnly = true): array
    {
        $packages = [];

        foreach ($this->entries($enabledOnly) as $package => $capabilities) {
            if (in_array($token, $capabilities['provides'], true)) {
                $packages[] = $package;
            }
        }

        return $packages;
    }

    /**
     * First package providing the token, or null.
     */
    public function firstProvider(string $token, bool $enabledOnly = true): ?string
    {
        $providers = $this->providersOf($token, $enabledOnly);

        return $providers[0] ?? null;
    }

    /**
     * Whether any (optionally enabled) package provides the token.
     */
    public function hasProvider(string $token, bool $enabledOnly = true): bool
    {
        return $this->firstProvider($token, $enabledOnly) !== null;
    }

    /**
     * Driver names registered under a family (e.g. `person.user.driver` → `['local','google']`).
     *
     * @return list<string>
     */
    public function driversOf(string $family, bool $enabledOnly = true): array
    {
        $names = [];

        foreach ($this->entries($enabledOnly) as $capabilities) {
            foreach ($capabilities['drivers'][$family] ?? [] as $name) {
                if (! in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Capability blob for one package, or null when untracked / no capabilities key.
     *
     * @return ModuleCapabilitiesShape|null
     */
    public function capabilitiesFor(string $package): ?array
    {
        $state = $this->store->get($package);

        if ($state === null || ! array_key_exists('capabilities', $state)) {
            return null;
        }

        return ModuleCapabilities::normalize($state['capabilities']);
    }

    /**
     * Required tokens declared by the package that no enabled provider currently supplies.
     *
     * @return list<string>
     */
    public function missingRequirements(string $package): array
    {
        $capabilities = $this->capabilitiesFor($package);

        if ($capabilities === null) {
            return [];
        }

        $missing = [];

        foreach ($capabilities['requires'] as $token) {
            if (! $this->hasProvider($token, enabledOnly: true)) {
                $missing[] = $token;
            }
        }

        return $missing;
    }

    /**
     * @return array<string, ModuleCapabilitiesShape>
     */
    protected function entries(bool $enabledOnly): array
    {
        $out = [];

        foreach ($this->store->all() as $package => $state) {
            if (! array_key_exists('capabilities', $state)) {
                continue;
            }

            $capabilities = ModuleCapabilities::normalize($state['capabilities']);

            // Library modules (ports/tokens) always contribute when registered,
            // even if Modulus has not flipped their enabled flag.
            if (
                $enabledOnly
                && $state['enabled'] !== true
                && $capabilities['kind'] !== ModuleCapabilities::KIND_LIBRARY
            ) {
                continue;
            }

            $out[$package] = $capabilities;
        }

        return $out;
    }
}
