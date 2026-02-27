<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Refresh token cookie
    |--------------------------------------------------------------------------
    |
    | Refresh tokens are stored in an HttpOnly cookie and rotated on use.
    | The raw refresh token is never stored in the database; only a SHA-256 hash.
    |
    */
    'cookie' => [
        'name' => env('REFRESH_TOKEN_COOKIE', 'rms_refresh'),
        'ttl_days' => (int) env('REFRESH_TOKEN_TTL_DAYS', 30),
        'path' => env('REFRESH_TOKEN_COOKIE_PATH', '/'),
        'domain' => env('REFRESH_TOKEN_COOKIE_DOMAIN'),
        'same_site' => env('REFRESH_TOKEN_COOKIE_SAMESITE', 'lax'), // lax | strict | none
    ],

    /*
    |--------------------------------------------------------------------------
    | Rotation grace window (seconds)
    |--------------------------------------------------------------------------
    |
    | To tolerate near-parallel refresh requests, a refresh token that has been
    | revoked due to rotation can be reused for a short window, as long as it
    | points to a successor token via rotated_to_id. Expired tokens are never
    | eligible, and reuse is never allowed after the grace window.
    |
    */
    'rotation_grace_seconds' => (int) env('REFRESH_TOKEN_ROTATION_GRACE_SECONDS', 30),
];

