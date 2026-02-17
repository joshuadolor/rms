<?php

return [

    /*
    |--------------------------------------------------------------------------
    | E2E cleanup email patterns
    |--------------------------------------------------------------------------
    |
    | Used by e2e:cleanup-users (and the dev-only cleanup endpoint) to delete
    | users whose email matches one of these SQL LIKE patterns. Add patterns
    | here when you introduce new E2E email formats (e.g. e2e-resend-%@example.com).
    |
    */

    'cleanup_email_patterns' => [
        'e2e-%@example.com',
    ],

];
