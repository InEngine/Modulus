<?php

use InEngine\Modulus\State\FileModulusStateStore;

// config for InEngine/Modulus
return [

    /*
    |--------------------------------------------------------------------------
    | Module state store
    |--------------------------------------------------------------------------
    |
    | Class that implements InEngine\Modulus\Contracts\ModulusStateStore.
    | Defaults to the flat JSON file store. Swap this for another implementation
    | (Redis, database, etc.) without changing Modulus orchestration code.
    |
    */
    'store' => FileModulusStateStore::class,

    /*
    |--------------------------------------------------------------------------
    | Module state file
    |--------------------------------------------------------------------------
    |
    | Used by FileModulusStateStore. Absolute path to the JSON file that tracks
    | which Composer module packages are enabled or disabled. Defaults under
    | storage/ (typically gitignored). Writes use a temporary file and an
    | atomic rename.
    |
    */
    'state_path' => storage_path('modulus/modules.json'),

];
