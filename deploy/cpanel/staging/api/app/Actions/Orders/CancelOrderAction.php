<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OrderStateMachine;
use Illuminate\Support\Facades\DB;

class CancelOrderAction
{
    public function __construct(
        private OrderStateMachine $state,
        private AuditLogger $audit,
    ) {}

    public function execute(Order $order, User $actor, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $actor, $reason) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $order->status;

            if (! $from->canTransitionTo(OrderStatus::Cancelled)) {
                throw new DomainException('This order can no longer be cancelled.');
            }

            $this->state->transition($order, OrderStatus::Cancelled);

            if ($order->table_id) {
                RestaurantTable::query()
                    ->whereKey($order->table_id)
                    ->where('current_order_id', $order->id)
                    ->update([
                        'current_order_id' => null,
                        'status' => TableStatus::Available,
                    ]);
            }

            $this->audit->record(
                'order.cancelled',
                $order,
                ['status' => $from->value],
                ['status' => OrderStatus::Cancelled->value, 'reason' => $reason],
                $actor,
            );

            return $order->fresh();
        });
    }
}
