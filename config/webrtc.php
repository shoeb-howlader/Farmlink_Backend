<?php

return [
    'coturn' => [
        'host' => env('COTURN_HOST', '127.0.0.1'),
        'port' => (int) env('COTURN_PORT', 3478),
        'tls_port' => (int) env('COTURN_TLS_PORT', 5349),
        'realm' => env('COTURN_REALM', 'farmlinkcare.com'),
        'secret' => env('COTURN_SECRET', ''),
        'ttl' => (int) env('COTURN_TTL', 86400), // 24 hours
    ],
];
