<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Commands\Concerns\InteractsWithModulus;
use Throwable;

class PublishModuleCommand extends Command
{
    use InteractsWithModulus;

    protected $signature = 'modulus:publish {package : Composer package name}';

    protected $description = 'Publish module config/assets into the host (install/update path)';

    public function handle(): int
    {
        $package = (string) $this->argument('package');

        try {
            $this->modulus()->publish($package);
        } catch (Throwable $e) {
            return $this->reportModulusException($e);
        }

        $this->info("Published [{$package}].");

        return self::SUCCESS;
    }
}
