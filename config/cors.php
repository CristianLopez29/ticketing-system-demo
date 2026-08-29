<?php

declare(strict_types=1);

// Read from env directly: config files are loaded alphabetically, so config('security.*')
// is still null at the moment this file is required.
$origins = env('CORS_ALLOWED_ORIGINS', '*');

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Published so the policy is a decision rather than an inherited framework default,
    | which was "*". A wildcard origin is defensible here only because authentication is
    | bearer-token and never cookie-based, so supports_credentials stays false: a hostile
    | page can issue the request but cannot make the browser attach anyone's credentials.
    |
    | Narrow CORS_ALLOWED_ORIGINS to explicit origins as soon as a browser client exists.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => is_string($origins) && $origins !== '*'
        ? array_values(array_filter(array_map('trim', explode(',', $origins))))
        : ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Idempotency-Key', 'X-Correlation-ID'],

    'exposed_headers' => ['X-Correlation-ID'],

    'max_age' => 3600,

    'supports_credentials' => false,

];
