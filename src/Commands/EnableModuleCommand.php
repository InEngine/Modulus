<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Commands\Concerns\InteractsWithModulus;
use Throwable;

class EnableModuleCommand extends Command
{
    use InteractsWithModulus;

    protected $signature = 'modulus:enable {package : Composer package name (e.g. inengine/alert)}';

    protected $description = 'Enable a Modulus module (deps, migrate, discovery sync)';

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
