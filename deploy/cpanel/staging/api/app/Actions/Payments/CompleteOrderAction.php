<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\TableStatus;
use App\Events\OrderCompleted;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderStateMachine;
use App\Support\Money;

class CompleteOrderAction
{
    public function __construct(
        private OrderStateMachine $state,
        private InventoryService $inventory,
    ) {}

    public function execute(Order $order, User $actor): Order
    {
        $order->load('payments');

        if ($order->status === OrderStatus::Completed) {
            return $order;
        }

        if ($order->status === OrderStatus::Cancelled) {
            throw new DomainException('A cancelled order cannot be completed.');
        }

        if (Money::compare($order->balanceDue(), '0') > 0) {
            throw new DomainException('The order still has an outstanding balance.');
        }

        if ($order->type === OrderType::Takeaway && $order->status !== OrderStatus::Served) {
            $this->advanceTakeaway($order);
            $order->refresh();
        }

        if ($order->status !== OrderStatus::Served) {
            throw new DomainException('Serve the order before completing it.');
        }

        $this->state->transition($order, OrderStatus::Completed);

        if ($order->table_id) {
            RestaurantTable::query()
                ->whereKey($order->table_id)
                ->where('current_order_id', $order->id)
                ->update([
                    'current_order_id' => null,
                    'status' => TableStatus::Available,
                ]);
        }

        $this->inventory->deductForOrder($order, $actor);

        OrderCompleted::dispatch($order->fresh());

        return $order->fresh(['payments', 'items.modifiers']);
    }

    private function advanceTakeaway(Order $order): void
    {
        $path = [
            OrderStatus::Draft->value => OrderStatus::Submitted,
            OrderStatus::Submitted->value => OrderStatus::Preparing,
            OrderStatus::Preparing->value => OrderStatus::Ready,
            OrderStatus::Ready->value => OrderStatus::Served,
        ];

        while (isset($path[$order->status->value])) {
            $this->state->transition($order, $path[$order->status->value]);
            $order->refresh();
        }
    }
}
