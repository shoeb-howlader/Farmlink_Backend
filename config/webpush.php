<?php

return [
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@farmlinkcare.com'),
        'public_key' => env('VAPID_PUBLIC_KEY', 'BEY0AiAmS6SbzvcnN7l0o1loTpWAvSpg-ilAjRYmBWvjUKwk-mOlr0SjymDygKNRHnHO6qT8VijNCXmz25KkdwA'),
        'private_key' => env('VAPID_PRIVATE_KEY', 'BrDpEk6v6ePa4VId8LogaQJy65JUsFPVEZPf_BINOIc'),
    ],
];
