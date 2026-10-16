<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpenseCategory: string
{
    case Rent = 'rent';
    case Utilities = 'utilities';
    case Salary = 'salary';
    case Gas = 'gas';
    case Transport = 'transport';
    case Maintenance = 'maintenance';
    case Cleaning = 'cleaning';
    case Supplies = 'supplies';
    case Miscellaneous = 'miscellaneous';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $category): string => $category->value, self::cases());
    }
}
