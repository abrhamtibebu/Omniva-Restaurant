<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryTransactionType: string
{
    case Purchase = 'purchase';
    case Usage = 'usage';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case Waste = 'waste';
    case Return = 'return';

    public function isInbound(): bool
    {
        return in_array($this, [self::Purchase, self::AdjustmentIn, self::Return], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }
}
