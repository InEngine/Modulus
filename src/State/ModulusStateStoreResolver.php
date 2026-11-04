<?php

namespace InEngine\Modulus\State;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Exceptions\InvalidModulusStateStoreException;

/**
 * Resolves {@see ModulusStateStore} from `modulus.store` config.
 */
final class ModulusStateStoreResolver
{
    /**
     * Build the configured state store (file default or custom binding).
     *
     * @throws InvalidModulusStateStoreException When config is missing, invalid, or unbound.
     */
    public static function resolve(Application $app): ModulusStateStore
    {
        $storeClass = $app['config']->get('modulus.store', FileModulusStateStore::class);

        if (! is_string($storeClass) || $storeClass === '' || ! class_exists($storeClass)) {
            throw new InvalidModulusStateStoreException(
                'Config [modulus.store] must be a concrete class name implementing '.ModulusStateStore::class.'.'
            );
        }

        if (! is_a($storeClass, ModulusStateStore::class, true)) {
            throw new InvalidModulusStateStoreException(
                "Config [modulus.store] class [{$storeClass}] must implement ".ModulusStateStore::class.'.'
            );
        }

        if ($storeClass === FileModulusStateStore::class) {
            $files = $app->bound('files') ? $app['files'] : new Filesystem;

            return FileModulusStateStoreFactory::make($app['config'], $files);
        }

        $store = $app->make($storeClass);

        if (! $store instanceof ModulusStateStore) {
            throw new InvalidModulusStateStoreException(
                'Resolved [modulus.store] instance must implement '.ModulusStateStore::class.'.'
            );
        }

        return $store;
    }
}
