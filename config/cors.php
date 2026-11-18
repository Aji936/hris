<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configured to support multi-device access across local Wi-Fi / LAN IPs
    | and tunnel domains while preserving CSRF & session cookie credentials.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'attendance/*', 'payroll/*', 'core-hr/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Inertia'],

    'max_age' => 0,

    'supports_credentials' => true,

];
