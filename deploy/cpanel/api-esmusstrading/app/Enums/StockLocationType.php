<?php

declare(strict_types=1);

namespace App\Enums;

enum StockLocationType: string
{
    case Store = 'store';
    case Bar = 'bar';
    case Kitchen = 'kitchen';

    public function label(): string
    {
        return match ($this) {
            self::Store => 'Store',
            self::Bar => 'Bar',
            self::Kitchen => 'Kitchen',
        };
    }
}
