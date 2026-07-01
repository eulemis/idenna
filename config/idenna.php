<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contraseña por defecto — usuarios importados como registradores
    |--------------------------------------------------------------------------
    |
    | Usada por: php artisan nna:import-registradores
    | Sobrescribible con --password= o IMPORT_REGISTRAR_DEFAULT_PASSWORD en .env
    |
    */
    'import_registrar_default_password' => env('IMPORT_REGISTRAR_DEFAULT_PASSWORD', 'Registrador123!'),

];
