<?php

return [
    'store_id' => env('SSLCZ_STORE_ID', env('SSLCOMMERZ_STORE_ID')),
    'store_password' => env('SSLCZ_STORE_PASSWORD', env('SSLCOMMERZ_STORE_PASSWORD')),
    'is_sandbox' => filter_var(env('SSLCZ_TESTMODE', env('SSLCOMMERZ_IS_SANDBOX', true)), FILTER_VALIDATE_BOOLEAN),

    'sandbox_url' => 'https://sandbox.sslcommerz.com',
    'live_url' => 'https://securepay.sslcommerz.com',

    'session_endpoint' => '/gwprocess/v4/api.php',
    'validation_endpoint' => '/validator/api/validationserverAPI.php',
    'query_endpoint' => '/validator/api/merchantTransIDvalidationAPI.php',
    'refund_endpoint' => '/validator/api/merchantTransIDvalidationAPI.php',

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    'backend_url' => env('APP_URL', 'http://127.0.0.1:8000'),

    // Timeout in minutes for uncompleted pending_payment orders
    'order_timeout_minutes' => env('SSLCOMMERZ_TIMEOUT_MINUTES', 45),
];
