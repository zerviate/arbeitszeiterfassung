<?php

$configuredOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
)));

$defaultOrigins = [
    (string) env('APP_URL', 'http://localhost'),
    'http://localhost',
    'http://127.0.0.1',
];

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $configuredOrigins !== []
        ? $configuredOrigins
        : $defaultOrigins,

    'allowed_origins_patterns' => [
        '/^https?:\/\/[a-z0-9-]+\.ddev\.site$/',
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-CSRF-TOKEN',
        'X-Requested-With',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
