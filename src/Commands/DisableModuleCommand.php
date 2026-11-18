<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Commands\Concerns\InteractsWithModulus;
use Throwable;

class DisableModuleCommand extends Command
{
    use InteractsWithModulus;

    protected $signature = 'modulus:disable {package : Composer package name}';

    protected $description = 'Disable a Modulus module (non-destructive)';

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
