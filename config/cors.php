<?php

return [
    'paths' => ['api/publik/*'],
    'allowed_methods' => ['GET', 'POST'],
    'allowed_origins' => [
        env('WEBSITE_PUBLIK_URL', 'https://bagianorganisasi.anambaskab.go.id'),
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => [
        'Content-Type',
        'X-Publik-API-Key',
        'X-Request-Source',
    ],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
