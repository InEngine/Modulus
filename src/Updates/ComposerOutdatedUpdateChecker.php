<?php

namespace InEngine\Modulus\Updates;

use Composer\Semver\Semver;
use Illuminate\Support\Facades\Process;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleUpdateChecker;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\DataTransferObjects\AvailableModuleUpdate;
use InEngine\Modulus\ModuleServiceProvider;
use InEngine\Modulus\Upgrades\SemverBumpClassifier;

/**
 * Uses `composer outdated --format=json` for direct deps, filtered to Modulus modules.
 */
final class ComposerOutdatedUpdateChecker implements ModuleUpdateChecker
{
    public function __construct(
        protected ModulusStateStore $store,
        protected InstalledPackageLocator $installedPackages,
        protected SemverBumpClassifier $bumps = new SemverBumpClassifier,
        protected string $workingDirectory = '',
    ) {
        if ($this->workingDirectory === '') {
            $this->workingDirectory = base_path();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function availableUpdates(): array
    {
        $raw = $this->composerOutdated();

        if ($raw === []) {
            return [];
        }

        $updates = [];

        foreach ($raw as $row) {
            $name = $row['name'] ?? null;
            $installed = $row['version'] ?? $row['installed'] ?? null;
            $latest = $row['latest'] ?? null;

            if (! is_string($name) || $name === '' || ! is_string($installed) || ! is_string($latest)) {
                continue;
            }

            if (! $this->isModulusModule($name)) {
                continue;
            }

            $installed = ltrim($installed, 'v');
            $latest = ltrim($latest, 'v');
            $constraint = $this->installedPackages->rootConstraint($name);
            $bump = $this->bumps->classify($installed, $latest);
            $within = $constraint !== null && $this->satisfies($latest, $constraint);
            // Eligible for auto-apply when policy allows; config modulus.updates.auto_patch gates apply.
            $autoEligible = $bump === AvailableModuleUpdate::BUMP_PATCH
                && ($within || $constraint === null);

            $updates[] = new AvailableModuleUpdate(
                package: $name,
                installedVersion: $installed,
                latestVersion: $latest,
                bump: $bump,
                withinConstraint: $within,
                autoEligible: $autoEligible,
                rootConstraint: $constraint,
            );
        }

        return $updates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function composerOutdated(): array
    {
        try {
            $result = Process::path($this->workingDirectory)
                ->timeout(120)
                ->run(['composer', 'outdated', '--direct', '--format=json']);
        } catch (\Throwable) {
            return [];
        }

        if (! $result->successful()) {
            return [];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($result->output(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $installed = $decoded['installed'] ?? $decoded;

        if (! is_array($installed)) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        foreach ($installed as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    protected function satisfies(string $version, string $constraint): bool
    {
        try {
            return Semver::satisfies($version, $constraint);
        } catch (\UnexpectedValueException) {
            return false;
        }
    }

    protected function isModulusModule(string $package): bool
    {
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
}
