<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Commands\Concerns\InteractsWithModulus;
use Throwable;

class SeedModuleCommand extends Command
{
    use InteractsWithModulus;

    protected $signature = 'modulus:seed
        {package : Composer package name}
        {--force : Skip confirmation prompt}';

    protected $description = 'Run a module seeder (requires confirmation unless --force)';

    public function handle(): int
    {
        $package = (string) $this->argument('package');

        if (! $this->option('force') && ! $this->confirm("Run seeder for [{$package}]? This may modify data.", false)) {
            $this->comment('Cancelled.');

            return self::SUCCESS;
        }

        try {
            $this->modulus()->seed($package, confirmed: true);
        } catch (Throwable $e) {
            return $this->reportModulusException($e);
        }

        $this->info("Seeded [{$package}].");

        return self::SUCCESS;
    }
}
