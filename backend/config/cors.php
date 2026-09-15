<?php

$frontend = env('FRONTEND_URL', 'http://localhost:3000');

$origins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', $frontend)),
)));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins !== [] ? $origins : ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
     * Bearer-token SPA auth does not need credentialed cookies.
     * Keep false unless you switch to Sanctum cookie sessions.
     */
    'supports_credentials' => false,

];
