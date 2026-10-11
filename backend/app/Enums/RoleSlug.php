<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleSlug: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Cashier = 'cashier';
    case Waiter = 'waiter';
    case Kitchen = 'kitchen';
    case Barman = 'barman';
    case Storekeeper = 'storekeeper';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Manager => 'Manager',
            self::Cashier => 'Cashier',
            self::Waiter => 'Waiter',
            self::Kitchen => 'Kitchen Staff',
            self::Barman => 'Barman',
            self::Storekeeper => 'Storekeeper',
        };
    }

    /**
     * Admins bypass individual permission checks entirely.
     */
    public function hasFullAccess(): bool
    {
        return $this === self::Admin;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role): string => $role->value, self::cases());
    }

    /**
     * Accounts that stay on the general Staff page.
     *
     * @return list<string>
     */
    public static function staffDirectory(): array
    {
        return [self::Admin->value, self::Manager->value];
    }

    /**
     * Accounts that operate the client bono flow.
     *
     * @return list<string>
     */
    public static function bonoTeam(): array
    {
        return [
            self::Cashier->value,
            self::Waiter->value,
            self::Kitchen->value,
            self::Barman->value,
            self::Storekeeper->value,
        ];
    }
}
