<?php

namespace InEngine\Modulus\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InEngine\Modulus\ModulusServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base Orchestra Testbench case for the Modulus package test suite.
 *
 * Registers {@see ModulusServiceProvider} and points Modulus state at an isolated
 * temp JSON file per test so tests never touch a real `modules.json`.
 * Bound to all tests via `tests/Pest.php`.
 */
class TestCase extends Orchestra
{
    /**
     * Register package service providers with the Testbench application.
     *
     * @param  Application  $app
     * @return array<int, class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app)
    {
        return [
            ModulusServiceProvider::class,
        ];
    }

    /**
     * Configure the Testbench environment before the application boots.
     *
     * Uses the `testing` database connection, a unique temp `modulus.state_path`
     * with fsync disabled, and the `patch` upgrade policy with `auto_apply_installed` on.
     *
     * @param  Application  $app
     */
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
