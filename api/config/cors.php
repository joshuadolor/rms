<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. When using credentials (e.g. Sanctum), origins
    | cannot be '*' — you must list allowed origins explicitly.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => env('APP_ENV', 'production') === 'local'
        ? array_values(array_unique(array_filter(array_merge(
            array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))),
            [
                env('FRONTEND_URL', 'http://localhost:8080'),
                'http://localhost:8080',
                'http://localhost:8082',  // Playwright e2e default
                'http://localhost:5173',
                'http://127.0.0.1:8080',
                'http://127.0.0.1:8082',
                'http://127.0.0.1:5173',
            ]
        ))))
        : array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', '')))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
