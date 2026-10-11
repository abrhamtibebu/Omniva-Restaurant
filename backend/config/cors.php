<?php

/**
 * CORS for the Nuxt SPAs (Bearer tokens — credentials not required).
 *
 * Local:
 *   FRONTEND_URL=http://localhost:3000
 *   FRONTEND_BONO_URL=http://localhost:3001
 *   CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:3001
 *
 * Production (comma-separated for POS + Bono origins):
 *   CORS_ALLOWED_ORIGINS=https://pos.example.com,https://bono.example.com
 *
 * Use CORS_ALLOWED_ORIGINS=* to allow any origin (fine with Bearer auth).
 *
 * Important: config is cached on deploy (`config:cache`). After changing these
 * env vars on Render, trigger a Manual Deploy so the new values are baked in.
 */

$raw = env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', '*'));

if (trim((string) $raw) === '*' || trim((string) $raw) === '') {
    $origins = ['*'];
} else {
    $origins = array_values(array_filter(array_map(
        static fn (string $origin): string => rtrim(trim($origin), '/'),
        explode(',', (string) $raw),
    )));
}

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins !== [] ? $origins : ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 60 * 60 * 24,

    'supports_credentials' => false,

];
