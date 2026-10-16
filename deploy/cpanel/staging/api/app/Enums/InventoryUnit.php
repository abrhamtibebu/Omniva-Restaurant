<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryUnit: string
{
    case Kilogram = 'kg';
    case Gram = 'gram';
    case Liter = 'liter';
    case Milliliter = 'ml';
    case Piece = 'piece';
    case Pack = 'pack';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $unit): string => $unit->value, self::cases());
    }
}
