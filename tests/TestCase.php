<?php

namespace InEngine\Modulus\Tests;

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

        $statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-testbench-'.uniqid('', true).'.json';
        config()->set('modulus.state_path', $statePath);
        config()->set('modulus.state_fsync', false);
        config()->set('modulus.upgrades.default_constraint', 'patch');
        config()->set('modulus.upgrades.auto_apply_installed', true);
    }
}
