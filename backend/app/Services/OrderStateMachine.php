<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\DomainException;
use App\Models\Order;

class OrderStateMachine
{
    public function transition(Order $order, OrderStatus $target): Order
    {
        $current = $order->status;

        if (! $current->canTransitionTo($target)) {
            throw DomainException::trans('messages.order.cannot_transition', [
                'from' => $current->value,
                'to' => $target->value,
            ]);
        }

        $order->status = $target;

        if ($target === OrderStatus::Completed) {
            $order->completed_at = now();
        }

        if ($target === OrderStatus::Cancelled) {
            $order->cancelled_at = now();
        }

        $order->save();

        return $order;
    }
}
