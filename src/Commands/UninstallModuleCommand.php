<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Commands\Concerns\InteractsWithModulus;
use Throwable;

/**
 * Artisan `modulus:uninstall {package}` — remove a module from Modulus state (Composer remove is modulus-composer).
 *
 * Delegates to {@see \InEngine\Modulus\ModuleRepository::uninstall()}.
 */
class UninstallModuleCommand extends Command
{
    use InteractsWithModulus;

    protected $signature = 'modulus:uninstall {package : Composer package name}';

    protected $description = 'Remove a module from Modulus state (Composer remove is modulus-composer)';

    /** Forget the given package from state; returns failure on handled Modulus errors. */
    public function handle(): int
    {
        $package = (string) $this->argument('package');

        try {
            $this->modulus()->uninstall($package);
        } catch (Throwable $e) {
            return $this->reportModulusException($e);
        }

        $this->info("Forgot [{$package}] from Modulus state.");
        $this->comment('Composer remove is owned by inengine/modulus-composer (Step 10).');

        return self::SUCCESS;
    }
}
