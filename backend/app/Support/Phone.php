<?php

declare(strict_types=1);

namespace App\Support;

final class Phone
{
    /**
     * Normalize to digits only. Ethiopian mobiles become 2519xxxxxxxx.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        // 09xxxxxxxx → 2519xxxxxxxx
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '251'.substr($digits, 1);
        }

        // 9xxxxxxxx → 2519xxxxxxxx
        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '251'.$digits;
        }

        return $digits;
    }
}
