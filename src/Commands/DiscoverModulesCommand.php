<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Commands\Concerns\InteractsWithModulus;
use InEngine\Modulus\ModuleRepository;

/**
 * Artisan `modulus:discover` — scan Composer installs for Modulus modules and update state.
 *
 * Delegates to {@see ModuleRepository::discover()}.
 */
class DiscoverModulesCommand extends Command
{
    use InteractsWithModulus;

    protected $signature = 'modulus:discover';

    protected $description = 'Scan Composer installs for Modulus modules and update state';

    /** Run discovery and report how many packages were newly tracked or version-updated. */
    public function handle(): int
    {
        $changed = $this->modulus()->discover();
        $this->info("Discover complete. {$changed} package(s) newly tracked or version-updated.");

        return self::SUCCESS;
    }
}
