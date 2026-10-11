<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Void = 'void';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
