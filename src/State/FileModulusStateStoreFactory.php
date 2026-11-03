<?php

namespace InEngine\Modulus\State;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;

/**
 * Builds {@see FileModulusStateStore} from Modulus config.
 */
final class FileModulusStateStoreFactory
{
    /**
     * Create a file store using `modulus.state_path` and `modulus.state_fsync`.
     */
    public static function make(Repository $config, ?Filesystem $files = null): FileModulusStateStore
    {
        $path = $config->get('modulus.state_path', storage_path('modulus/modules.json'));

        if (! is_string($path) || $path === '') {
            throw new \InvalidArgumentException('Config [modulus.state_path] must be a non-empty string path.');
        }

        return new FileModulusStateStore(
            $path,
            $files ?? new Filesystem,
            (bool) $config->get('modulus.state_fsync', false),
        );
    }
}
