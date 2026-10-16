<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\RoleSlug;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::UsersView);
    }

    public function view(User $user, User $staff): bool
    {
        return $user->hasPermission(Permission::UsersView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::UsersManage);
    }

    public function update(User $user, User $staff): bool
    {
        if (! $user->hasPermission(Permission::UsersManage)) {
            return false;
        }

        if ($staff->role?->slug === RoleSlug::Admin->value && ! $user->isAdmin()) {
            return false;
        }

        return true;
    }

    public function delete(User $user, User $staff): bool
    {
        if ($staff->is($user)) {
            return false;
        }

        return $this->update($user, $staff);
    }
}
