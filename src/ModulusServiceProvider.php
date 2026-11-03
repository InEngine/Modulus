<?php

namespace InEngine\Modulus;

use InEngine\Modulus\Commands\ModulusCommand;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\State\FileModulusStateStore;
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
            ->hasCommand(ModulusCommand::class);
    }

    /**
     * Bind the file-backed module state store before the package boots.
     */
    public function packageRegistered(): void
    {
        $this->app->singleton(ModulusStateStore::class, function ($app): FileModulusStateStore {
            /** @var string $path */
            $path = $app['config']->get('modulus.state_path', storage_path('modulus/modules.json'));

            return new FileModulusStateStore($path);
        });
    }
}
