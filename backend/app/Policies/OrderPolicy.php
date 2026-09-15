<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::OrdersView)
            || $user->hasPermission(Permission::OrdersViewOwn);
    }

    public function view(User $user, Order $order): bool
    {
        if (! $user->canOperateBranch((int) $order->branch_id)) {
            return false;
        }

        if ($user->hasPermission(Permission::OrdersView)) {
            return true;
        }

        return $user->hasPermission(Permission::OrdersViewOwn)
            && $order->waiter_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::OrdersCreate);
    }

    public function update(User $user, Order $order): bool
    {
        if (! $this->view($user, $order)) {
            return false;
        }

        return $user->hasPermission(Permission::OrdersUpdate);
    }

    public function submit(User $user, Order $order): bool
    {
        return $this->view($user, $order) && $user->hasPermission(Permission::OrdersSubmit);
    }

    public function serve(User $user, Order $order): bool
    {
        return $this->view($user, $order) && $user->hasPermission(Permission::OrdersServe);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->view($user, $order) && $user->hasPermission(Permission::OrdersCancel);
    }

    public function discount(User $user, Order $order): bool
    {
        return $this->view($user, $order) && $user->hasPermission(Permission::OrdersDiscount);
    }

    public function pay(User $user, Order $order): bool
    {
        return $user->hasPermission(Permission::PaymentsCreate);
    }
}
