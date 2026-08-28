<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API Documentation Basic Auth
    |--------------------------------------------------------------------------
    |
    | Guards the Swagger UI outside local. Bearer-token middleware cannot work
    | here: a browser has no way to attach one, so it was redirected to /login
    | and the docs were unreachable in production.
    |
    */
    'docs' => [
        'protect' => (bool) env('L5_SWAGGER_PROTECT', env('APP_ENV') !== 'local'),
        'username' => env('DOCS_AUTH_USERNAME', 'docs'),
        'password' => env('DOCS_AUTH_PASSWORD'),
    ],

];
