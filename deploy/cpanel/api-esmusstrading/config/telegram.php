<?php

declare(strict_types=1);

return [

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'bot_username' => env('TELEGRAM_BOT_USERNAME'),

    /**
     * Reject initData older than this many seconds (Telegram recommends checking auth_date).
     */
    'auth_max_age_seconds' => (int) env('TELEGRAM_AUTH_MAX_AGE', 86400),

];
