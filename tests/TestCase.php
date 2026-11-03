<?php

namespace InEngine\Modulus\Tests;

use Illuminate\Support\Facades\Artisan;
use InEngine\Modulus\ModulusServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
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

    /**
     * A helper method to copy the balanced config file from fixtures to the testbench config dir for
     * running tests
     *
     * @return void
     */
    public function copyBalancedConfigFile($configPath, $configFileName, $configFixturesPath, $balancedConfigFileName)
    {

        if (file_exists($configPath.$configFileName)) {
            unlink($configPath.$configFileName);
        }
        copy($configFixturesPath.$balancedConfigFileName, $configPath.$configFileName);
        Artisan::call('config:clear');
    }

    /*
     * A helper method to copy the empty config file from fixtures to the testbench config dir for
     * running tests
     *
     * @param $configPath
     * @param $configFileName
     * @param $configFixturesPath
     * @param $balancedConfigFileName
     * @return void
     */
    public function copyEmptyConfigFile($configPath, $configFileName, $configFixturesPath, $emptyConfigFileName)
    {
        if (file_exists($configPath.$configFileName)) {
            unlink($configPath.$configFileName);
        }
        copy($configFixturesPath.$emptyConfigFileName, $configPath.$configFileName);
        Artisan::call('config:clear');
    }

    /**
     * A helper method to reset the config file and copy it to the testbench config dir
     *  after or before running tests
     *
     * @return void
     */
    public function resetConfig($configPath, $configFileName, $stubConfigFile)
    {
        if (file_exists($configPath.$configFileName)) {
            unlink($configPath.$configFileName);
        }
        copy($stubConfigFile, $configPath.$configFileName);

    }
}
