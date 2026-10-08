<?php

return [
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@farmlinkcare.com'),
        'public_key' => env('VAPID_PUBLIC_KEY', null),
        'private_key' => env('VAPID_PRIVATE_KEY', null),
    ],
];
