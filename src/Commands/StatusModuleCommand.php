<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Commands\Concerns\InteractsWithModulus;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\ModuleRepository;

/**
 * Artisan `modulus:status {package}` — show Composer vs Modulus state for one package.
 *
 * Combines {@see ModuleRepository::get()} with {@see InstalledPackageLocator}.
 */
class StatusModuleCommand extends Command
{
    use InteractsWithModulus;

    protected $signature = 'modulus:status {package : Composer package name}';

    protected $description = 'Show Composer vs Modulus state for one package';

    /** Print a status table for the given package; always returns success. */
    public function handle(InstalledPackageLocator $packages): int
    {
        $package = (string) $this->argument('package');
        $state = $this->modulus()->get($package);

        $this->table(['Field', 'Value'], [
            ['Package', $package],
            ['Tracked', $state === null ? 'no' : 'yes'],
            ['Enabled', $state === null ? '—' : ($state['enabled'] ? 'yes' : 'no')],
            ['State version', $state['version'] ?? '—'],
            ['Composer installed', $packages->isInstalled($package) ? 'yes' : 'no'],
            ['Composer version', $packages->version($package) ?? '—'],
            ['Root constraint', $packages->rootConstraint($package) ?? '—'],
            ['Denied', $this->modulus()->isDenied($package) ? 'yes' : 'no'],
        ]);

        return self::SUCCESS;
    }
}
