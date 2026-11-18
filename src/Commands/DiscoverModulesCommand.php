<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Commands\Concerns\InteractsWithModulus;

class DiscoverModulesCommand extends Command
{
    use InteractsWithModulus;

    protected $signature = 'modulus:discover';

    protected $description = 'Scan Composer installs for Modulus modules and update state';

    public function handle(): int
    {
        $changed = $this->modulus()->discover();
        $this->info("Discover complete. {$changed} package(s) newly tracked or version-updated.");

        return self::SUCCESS;
    }
}
