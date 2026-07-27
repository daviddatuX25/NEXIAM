<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Vite Dev Server
    |--------------------------------------------------------------------------
    |
    | We run the Vite dev server inside the Sail container, with port 5173
    | inside the container mapped to port 5174 on the host. By default,
    | Laravel's Vite helper assumes port 5173 on the host, which conflicts
    | with other projects and results in a blank page (JS never loads).
    |
    | Explicitly point the dev server URL at host port 5174 so the script
    | and HMR client resolve correctly when visiting APP_URL (8080).
    |
    */

    'dev_server' => [
        'url' => env('VITE_DEV_SERVER_URL', 'http://localhost:5174'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Build Path
    |--------------------------------------------------------------------------
    |
    | This is the directory where the production build assets are output.
    | We keep the default `build` path used by the Laravel Vite plugin.
    |
    */

    'build_path' => 'build',
];

