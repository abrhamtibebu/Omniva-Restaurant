<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Events\OrderSubmitted;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Services\OrderStateMachine;
use Illuminate\Support\Facades\DB;

class SubmitOrderAction
{
    public function __construct(private OrderStateMachine $state) {}

    public function execute(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()->with('items')->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->items->isEmpty()) {
                throw new DomainException('Add at least one item before sending to the kitchen.');
            }

            if ($order->status === OrderStatus::Draft) {
                $this->state->transition($order, OrderStatus::Submitted);
            } elseif (! $order->status->isOpen()) {
                throw new DomainException('This order can no longer be submitted.');
            }

            $order->items()->update(['submitted_to_kitchen' => true]);

            OrderSubmitted::dispatch($order->fresh(['items.modifiers', 'table', 'waiter']));

            return $order->fresh(['items.modifiers', 'table', 'waiter']);
        });
    }
}
