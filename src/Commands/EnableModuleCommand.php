<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Commands\Concerns\InteractsWithModulus;
use InEngine\Modulus\ModuleRepository;
use Throwable;

/**
 * Artisan `modulus:enable {package}` — enable a tracked module (deps, migrate, discovery sync).
 *
 * Delegates to {@see ModuleRepository::enable()}.
 */
class EnableModuleCommand extends Command
{
    use InteractsWithModulus;

    protected $signature = 'modulus:enable {package : Composer package name (e.g. inengine/alert)}';

    protected $description = 'Enable a Modulus module (deps, migrate, discovery sync)';

    /** Enable the given package; returns failure when dependencies are unmet or the module is missing. */
    public function handle(): int
    {
        $package = (string) $this->argument('package');

        try {
            $this->modulus()->enable($package);
        } catch (Throwable $e) {
            return $this->reportModulusException($e);
        }

        $this->info("Enabled [{$package}].");

        return self::SUCCESS;
    }
}
