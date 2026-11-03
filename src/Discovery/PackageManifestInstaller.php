<?php

namespace InEngine\Modulus\Discovery;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\PackageManifest;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\State\FileModulusStateStore;
use InEngine\Modulus\State\FileModulusStateStoreFactory;

/**
 * Binds {@see FilteringPackageManifest} before Laravel registers discovered providers.
 *
 * Hosts must call {@see self::install()} from `bootstrap/app.php` immediately after
 * `Application::configure(...)->create()` so filtering runs early enough.
 */
final class PackageManifestInstaller
{
    /**
     * Absolute path to the package's default config file.
     */
    public static function defaultConfigPath(): string
    {
        return dirname(__DIR__, 2).'/config/modulus.php';
    }

    /**
     * Register the early PackageManifest rebind hook on the application.
     */
    public static function install(Application $app): void
    {
        $app->beforeBootstrapping(RegisterProviders::class, function (Application $app): void {
            self::ensureConfig($app);
            self::ensureStateStore($app);

            $app->singleton(PackageManifest::class, function (Application $app): FilteringPackageManifest {
                /** @var ModulusStateStore $store */
                $store = $app->make(ModulusStateStore::class);

                $deny = $app['config']->get('modulus.discovery.deny', ['inengine/modulus']);
                $scope = $app['config']->get('modulus.discovery.scope', 'modules');

                if (! is_array($deny)) {
                    $deny = ['inengine/modulus'];
                }

                /** @var list<string> $denyList */
                $denyList = array_values(array_filter($deny, 'is_string'));

                if (! is_string($scope) || $scope === '') {
                    $scope = 'modules';
                }

                return new FilteringPackageManifest(
                    $app['files'],
                    $app->basePath(),
                    $app->getCachedPackagesPath(),
                    $store,
                    $scope,
                    $denyList,
                );
            });
        });
    }

    /**
     * Merge package defaults when the host has not published config yet.
     */
    protected static function ensureConfig(Application $app): void
    {
        $config = $app->make('config');

        if ($config->has('modulus.store')) {
            return;
        }

        $path = self::defaultConfigPath();

        if (! is_file($path)) {
            return;
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require $path;

        $config->set('modulus', $defaults);
    }

    /**
     * Bind the state store before ModulusServiceProvider registers (too late for discovery).
     */
    protected static function ensureStateStore(Application $app): void
    {
        if ($app->bound(ModulusStateStore::class)) {
            return;
        }

        $app->singleton(ModulusStateStore::class, function (Application $app): ModulusStateStore {
            $storeClass = $app['config']->get('modulus.store', FileModulusStateStore::class);

            if (! is_string($storeClass) || $storeClass === '' || ! class_exists($storeClass)) {
                throw new \InvalidArgumentException(
                    'Config [modulus.store] must be a concrete class name implementing '.ModulusStateStore::class.'.'
                );
            }

            if (! is_a($storeClass, ModulusStateStore::class, true)) {
                throw new \InvalidArgumentException(
                    "Config [modulus.store] class [{$storeClass}] must implement ".ModulusStateStore::class.'.'
                );
            }

            if ($storeClass === FileModulusStateStore::class) {
                return FileModulusStateStoreFactory::make($app['config'], $app['files']);
            }

            $store = $app->make($storeClass);

            if (! $store instanceof ModulusStateStore) {
                throw new \InvalidArgumentException(
                    'Resolved [modulus.store] instance must implement '.ModulusStateStore::class.'.'
                );
            }

            return $store;
        });
    }
}
