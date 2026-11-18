<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configured to support multi-device access across local Wi-Fi / LAN IPs,
    | CapacitorJS Android/iOS WebViews (capacitor://localhost, http://localhost,
    | https://localhost), and tunnel domains while preserving session cookies.
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'dashboard',
        'attendance',
        'attendance/*',
        'payroll/*',
        'core-hr/*',
        'rbac/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'capacitor://localhost',
        'http://localhost',
        'https://localhost',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'http://192.168.4.9:8000',
    ],

    'allowed_origins_patterns' => [
        '#^https?://192\.168\.\d+\.\d+(:\d+)?$#',
        '#^https?://10\.\d+\.\d+\.\d+(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Inertia', 'Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => true,

];
