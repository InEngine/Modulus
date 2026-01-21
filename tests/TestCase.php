<?php

namespace InEngine\Modulus\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use InEngine\Modulus\ModulusServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Support\Facades\Artisan;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'InEngine\\Modulus\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->artisan('vendor:publish', ['--provider' => ModulusServiceProvider::class]);

    }

    protected function getPackageProviders($app)
    {
        return [
            ModulusServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
         foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
         }
         */
    }

    public function copyBalancedConfigFile($configPath, $configFileName, $configFixturesPath, $balancedConfigFileName) {

        if(file_exists($configPath . $configFileName)) {
            unlink($configPath . $configFileName);
        }
        copy($configFixturesPath . $balancedConfigFileName, $configPath . $configFileName);
        Artisan::call('config:clear');
    }

    public function copyEmptyConfigFile($configPath, $configFileName, $configFixturesPath, $emptyConfigFileName) {
        if(file_exists($configPath . $configFileName)) {
            unlink($configPath . $configFileName);
        }
        copy($configFixturesPath . $emptyConfigFileName, $configPath . $configFileName);

    }

    public function resetConfig($configPath, $configFileName, $stubConfigFile)
    {
        if(file_exists($configPath . $configFileName)) {
            unlink($configPath . $configFileName);
        }
        copy($stubConfigFile, $configPath . $configFileName);


    }
}
