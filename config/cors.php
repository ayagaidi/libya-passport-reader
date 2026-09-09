<?php

$origins = array_values(array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env('DOCUMENT_API_CORS_ORIGINS', 'http://localhost:3000,http://localhost:5173,http://localhost:8000')),
)));

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => [
        'Accept',
        'Content-Type',
        'Origin',
        'X-API-Key',
        'X-Requested-With',
    ],
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Scope',
        'X-Demo-Global-RateLimit-Limit',
        'X-Demo-Global-RateLimit-Remaining',
        'Retry-After',
    ],
    'max_age' => 600,
    'supports_credentials' => false,
];
