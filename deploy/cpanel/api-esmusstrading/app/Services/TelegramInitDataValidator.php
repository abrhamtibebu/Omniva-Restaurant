<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use Illuminate\Support\Facades\Config;

/**
 * Validates Telegram Mini App initData (HMAC-SHA-256 WebApp algorithm).
 *
 * @see https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
 */
class TelegramInitDataValidator
{
    /**
     * @return array{id: int, username: ?string, first_name: ?string, last_name: ?string, auth_date: int}
     */
    public function validate(string $initData): array
    {
        $token = (string) Config::get('telegram.bot_token');
        if ($token === '') {
            throw DomainException::trans('messages.telegram.bot_not_configured');
        }

        $pairs = [];
        parse_str($initData, $pairs);

        if (! is_array($pairs) || empty($pairs['hash']) || empty($pairs['user'])) {
            throw DomainException::trans('messages.telegram.invalid_login_data');
        }

        $hash = (string) $pairs['hash'];
        unset($pairs['hash']);

        ksort($pairs);
        $dataCheckString = collect($pairs)
            ->map(fn ($value, $key) => $key.'='.$value)
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $token, 'WebAppData', true);
        $calculated = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($calculated, $hash)) {
            throw DomainException::trans('messages.telegram.invalid_signature');
        }

        $authDate = (int) ($pairs['auth_date'] ?? 0);
        $maxAge = (int) Config::get('telegram.auth_max_age_seconds', 86400);
        if ($authDate <= 0 || (time() - $authDate) > $maxAge) {
            throw DomainException::trans('messages.telegram.login_expired');
        }

        $user = json_decode((string) $pairs['user'], true);
        if (! is_array($user) || empty($user['id'])) {
            throw DomainException::trans('messages.telegram.user_missing');
        }

        return [
            'id' => (int) $user['id'],
            'username' => isset($user['username']) ? (string) $user['username'] : null,
            'first_name' => isset($user['first_name']) ? (string) $user['first_name'] : null,
            'last_name' => isset($user['last_name']) ? (string) $user['last_name'] : null,
            'auth_date' => $authDate,
        ];
    }
}
