<?php

return [
    'coturn' => [
        'host' => env('COTURN_HOST', '165.99.219.252'),
        'port' => (int) env('COTURN_PORT', 3478),
        'tls_port' => (int) env('COTURN_TLS_PORT', 5349),
        'realm' => env('COTURN_REALM', 'farmlinkcare.com'),
        'secret' => env('COTURN_SECRET', 'farmlink_bdix_turn_secret_2026_x89q4w'),
        'ttl' => (int) env('COTURN_TTL', 86400), // 24 hours
    ],
];
