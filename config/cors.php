<?php

$defaultOrigins = [
    'https://farmlinkcare.com',
    'https://www.farmlinkcare.com',
    'https://app.farmlinkcare.com',
    'https://api.farmlinkcare.com',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
];

if ($frontendUrl = env('FRONTEND_URL')) {
    $defaultOrigins[] = rtrim($frontendUrl, '/');
}

if ($appUrl = env('APP_URL')) {
    $defaultOrigins[] = rtrim($appUrl, '/');
}

$envOrigins = env('CORS_ALLOWED_ORIGINS')
    ? array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS'))))
    : [];

$allowedOrigins = array_values(array_unique(array_merge($defaultOrigins, $envOrigins)));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [
        '#^https?://(.*\.)?farmlinkcare\.com(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
