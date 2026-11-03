<?php

// config for InEngine/Modulus
return [

    /*
    |--------------------------------------------------------------------------
    | Module state file
    |--------------------------------------------------------------------------
    |
    | Absolute path to the JSON file that tracks which Composer module packages
    | are enabled or disabled. Defaults under storage/ (typically gitignored).
    | Writes use a temporary file and an atomic rename.
    |
    */
    'state_path' => storage_path('modulus/modules.json'),

];
