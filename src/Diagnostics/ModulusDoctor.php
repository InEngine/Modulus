<?php

namespace InEngine\Modulus\Diagnostics;

use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleDependencyGuard;
use InEngine\Modulus\Contracts\ModuleUpdateChecker;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\DataTransferObjects\AvailableModuleUpdate;
use InEngine\Modulus\DataTransferObjects\DoctorFinding;
use InEngine\Modulus\DataTransferObjects\DoctorReport;
use InEngine\Modulus\Exceptions\CorruptModulusStateException;
use InEngine\Modulus\Exceptions\UnmetModuleDependencyException;
use InEngine\Modulus\ModuleServiceProvider;
use InEngine\Modulus\Upgrades\UpgradeConstraintPolicy;

/**
 * Builds structured diagnostics for `modulus:doctor` and modulus-ui.
 */
final class ModulusDoctor
{
    public function __construct(
        protected ModulusStateStore $store,
        protected InstalledPackageLocator $installedPackages,
        protected ModuleDependencyGuard $dependencies,
        protected UpgradeConstraintPolicy $constraintPolicy,
        protected ?ModuleUpdateChecker $updateChecker = null,
        protected bool $includeUpdates = true,
        /** @var list<string> */
        protected array $deny = ['inengine/modulus'],
    ) {}

    /**
     * Inspect state, Composer installs, constraints, dependencies, and updates.
     *
     * A corrupt state file short-circuits into a single error finding with `stateHealthy` false.
     */
    public function run(): DoctorReport
    {
        $findings = [];
        $stateHealthy = true;

        try {
            $all = $this->store->all();
        } catch (CorruptModulusStateException $e) {
            return new DoctorReport(
                findings: [
                    new DoctorFinding(
                        code: 'state_corrupt',
                        severity: DoctorFinding::SEVERITY_ERROR,
                        message: $e->getMessage(),
                        suggestions: ['Repair or remove the Modulus state file, then run modulus:discover.'],
                    ),
                ],
                stateHealthy: false,
                phpVersion: PHP_VERSION,
                defaultConstraintPolicy: $this->constraintPolicy->policy(),
            );
        }

        foreach ($all as $package => $state) {
            if (in_array($package, $this->deny, true)) {
                continue;
            }

            $enabled = $state['enabled'] === true;
            $installed = $this->installedPackages->isInstalled($package);

            if (! $installed) {
                $findings[] = new DoctorFinding(
                    code: 'missing_package',
                    severity: $enabled ? DoctorFinding::SEVERITY_ERROR : DoctorFinding::SEVERITY_WARNING,
                    message: ($enabled ? 'Enabled' : 'Disabled')." module [{$package}] is tracked but not installed via Composer.",
                    package: $package,
                    suggestions: [
                        'Install via modulus-composer / composer require (then modulus:discover).',
                        'Supply a path or VCS repository and require it.',
                        'Substitute another module that fulfills the same role, then modulus:forget the missing package.',
                    ],
                );

                continue;
            }

            $constraint = $this->installedPackages->rootConstraint($package);

            if ($this->constraintPolicy->constraintAllowsUnapprovedBumps($constraint)) {
                $installedVersion = $this->installedPackages->version($package) ?? 'x.y.z';
                $findings[] = new DoctorFinding(
                    code: 'constraint_too_loose',
                    severity: DoctorFinding::SEVERITY_WARNING,
                    message: "Host constraint for [{$package}] is [{$constraint}], which is looser than the default {$this->constraintPolicy->policy()} policy.",
                    package: $package,
                    suggestions: [
                        'Prefer '.$this->constraintPolicy->suggestConstraint($installedVersion).' so only patches (same minor) apply until an admin approves a bump in modulus-ui or CLI.',
                        'Approve a minor/major upgrade explicitly; modulus-composer will rewrite the constraint and update the lock.',
                    ],
                );
            }

            if ($enabled) {
                try {
                    $this->dependencies->assertCanEnable($package);
                } catch (UnmetModuleDependencyException $e) {
                    $findings[] = new DoctorFinding(
                        code: 'dependency_violation',
                        severity: DoctorFinding::SEVERITY_ERROR,
                        message: $e->getMessage(),
                        package: $package,
                        suggestions: ['Enable required Modulus modules first, or install missing Composer packages.'],
                    );
                }

                $attention = $this->manualAttentionFor($package);

                if ($attention !== []) {
                    $findings[] = new DoctorFinding(
                        code: 'manual_attention',
                        severity: DoctorFinding::SEVERITY_INFO,
                        message: "Module [{$package}] reports manual attention items for operators.",
                        package: $package,
                        suggestions: $attention,
                    );
                }
            }
        }

        if ($this->includeUpdates && $this->updateChecker !== null) {
            foreach ($this->updateChecker->availableUpdates() as $update) {
                $findings[] = $this->findingForUpdate($update);
            }
        }

        return new DoctorReport(
            findings: $findings,
            stateHealthy: $stateHealthy,
            phpVersion: PHP_VERSION,
            defaultConstraintPolicy: $this->constraintPolicy->policy(),
        );
    }

    /** Convert an available update into an `update_available` finding (info for patches, warning otherwise). */
    protected function findingForUpdate(AvailableModuleUpdate $update): DoctorFinding
    {
        $severity = $update->isNonBreaking()
            ? DoctorFinding::SEVERITY_INFO
            : DoctorFinding::SEVERITY_WARNING;

        $suggestions = [
            'CLI: php artisan modulus:outdated',
            $update->autoEligible
                ? 'CLI: php artisan modulus:update --auto (patch auto-apply when enabled)'
                : 'Approve via modulus-ui / modulus:upgrade --approve='.$update->latestVersion.' then modulus-composer',
        ];

        return new DoctorFinding(
            code: 'update_available',
            severity: $severity,
            message: "Update available for [{$update->package}]: {$update->installedVersion} → {$update->latestVersion} ({$update->bump}).",
            package: $update->package,
            suggestions: $suggestions,
        );
    }

    /**
     * @return list<string>
     */
    protected function manualAttentionFor(string $package): array
    {
        foreach ($this->installedPackages->laravelProviderClasses($package) as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, ModuleServiceProvider::class)) {
                continue;
            }

            /** @var ModuleServiceProvider $provider */
            $provider = new $class(app());

            $items = [];

            foreach ($provider->modulusManualAttentionItems() as $item) {
                if ($item !== '') {
                    $items[] = $item;
                }
            }

            return $items;
        }

        return [];
    }
}
