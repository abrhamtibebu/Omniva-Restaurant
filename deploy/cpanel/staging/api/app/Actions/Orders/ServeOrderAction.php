<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Payments\CompleteOrderAction;
use App\Enums\OrderStatus;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Services\OrderStateMachine;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ServeOrderAction
{
    public function __construct(
        private OrderStateMachine $state,
        private CompleteOrderAction $completeOrder,
    ) {}

    public function execute(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()->with('payments')->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== OrderStatus::Ready) {
                throw new DomainException('Only ready orders can be marked served.');
            }

            $this->state->transition($order, OrderStatus::Served);
            $order->refresh()->load('payments');

            if (Money::compare($order->balanceDue(), '0') <= 0 && Money::isPositive((string) $order->total)) {
                $this->completeOrder->execute($order, $order->waiter);
            }

            return $order->fresh(['items.modifiers', 'payments']);
        });
    }
}
