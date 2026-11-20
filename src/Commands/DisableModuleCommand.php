<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Commands\Concerns\InteractsWithModulus;
use InEngine\Modulus\ModuleRepository;
use Throwable;

/**
 * Artisan `modulus:disable {package}` — disable a tracked module (non-destructive).
 *
 * Delegates to {@see ModuleRepository::disable()}.
 */
class DisableModuleCommand extends Command
{
    use InteractsWithModulus;

    protected $signature = 'modulus:disable {package : Composer package name}';

    protected $description = 'Disable a Modulus module (non-destructive)';

    /** Disable the given package; returns failure when dependents or other Modulus errors block it. */
    public function handle(): int
    {
        $package = (string) $this->argument('package');

        try {
            $this->modulus()->disable($package);
        } catch (Throwable $e) {
            return $this->reportModulusException($e);
        }

        $this->info("Disabled [{$package}].");

        return self::SUCCESS;
    }
}
