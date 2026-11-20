<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Commands\Concerns\InteractsWithModulus;
use Throwable;

/**
 * Artisan `modulus:seed {package} {--force}` — run a module seeder after confirmation.
 *
 * Delegates to {@see \InEngine\Modulus\ModuleRepository::seed()}. `--force` skips the confirmation prompt.
 */
class SeedModuleCommand extends Command
{
    use InteractsWithModulus;

    protected $signature = 'modulus:seed
        {package : Composer package name}
        {--force : Skip confirmation prompt}';

    protected $description = 'Run a module seeder (requires confirmation unless --force)';

    /** Seed the given package; returns success when cancelled at the prompt, failure on handled Modulus errors. */
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
