<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Superadmin credentials (env)
    |--------------------------------------------------------------------------
    | Used by the seeder to create/update the superadmin user. Set before go-live.
    | Example (dev): admin@admin.com / p@55w0rd123!
    */
    'email' => env('SUPERADMIN_EMAIL', ''),
    'password' => env('SUPERADMIN_PASSWORD', ''),
];
