<?php

namespace InEngine\Modulus;

use InEngine\Modulus\Commands\ModulusCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ModulusServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('modulus')
            ->hasConfigFile(['modulus', 'modules'])
            ->hasViews()
            ->hasMigration('create_modulus_table')
            ->hasCommand(ModulusCommand::class);
    }
}
