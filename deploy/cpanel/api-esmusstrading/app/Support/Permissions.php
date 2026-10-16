<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Permission;
use App\Enums\RoleSlug;

final class Permissions
{
    /**
     * @return list<string>
     */
    public static function forRole(?string $slug): array
    {
        if ($slug === RoleSlug::Admin->value) {
            return Permission::values();
        }

        $granted = config("permissions.{$slug}", []);

        return array_values(array_map(
            fn (mixed $permission): string => $permission instanceof Permission
                ? $permission->value
                : (string) $permission,
            $granted,
        ));
    }

    public static function roleHas(string $slug, Permission $permission): bool
    {
        return in_array($permission->value, self::forRole($slug), true);
    }
}
