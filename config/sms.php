<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default SMS Provider Chain
    |--------------------------------------------------------------------------
    | In production, primary is attempted first; on timeout, error, or credit
    | exhaustion, the system automatically falls back to secondary provider.
    */
    'default' => env('SMS_DRIVER', 'log'),
    'fallback' => env('SMS_FALLBACK_DRIVER', 'log'),

    'gateways' => [
        'log' => [
            'name' => 'Local / Testing Log Driver',
        ],

        'greenweb' => [
            'token' => env('GREENWEB_SMS_TOKEN'),
            'endpoint' => env('GREENWEB_SMS_ENDPOINT', 'https://api.greenweb.com.bd/api.php'),
            'timeout' => env('GREENWEB_SMS_TIMEOUT', 5),
        ],

        'mimosms' => [
            'api_key' => env('MIMOSMS_API_KEY'),
            'sender_id' => env('MIMOSMS_SENDER_ID', 'FarmLink'),
            'endpoint' => env('MIMOSMS_ENDPOINT', 'https://api.mimosms.com/api/SmsSending/SendSingleSms'),
            'timeout' => env('MIMOSMS_TIMEOUT', 5),
        ],
    ],
];
