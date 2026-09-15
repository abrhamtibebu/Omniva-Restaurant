<?php

declare(strict_types=1);

namespace App\Actions\Kitchen;

use App\Enums\KitchenStatus;
use App\Enums\OrderStatus;
use App\Events\OrderReady;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderStateMachine;
use Illuminate\Support\Facades\DB;

class UpdateKitchenStatusAction
{
    public function __construct(private OrderStateMachine $state) {}

    public function execute(OrderItem $item, KitchenStatus $target): OrderItem
    {
        return DB::transaction(function () use ($item, $target) {
            $item = OrderItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($item->order_id)->lockForUpdate()->firstOrFail();

            if (! $item->submitted_to_kitchen) {
                throw new DomainException('This item has not been sent to the kitchen yet.');
            }

            if (! $item->kitchen_status->canTransitionTo($target)) {
                throw new DomainException(
                    "Cannot move kitchen status from {$item->kitchen_status->value} to {$target->value}.",
                );
            }

            $item->kitchen_status = $target;
            $item->save();

            $order->load('items');

            $anyPreparing = $order->items->contains(
                fn (OrderItem $line) => $line->kitchen_status === KitchenStatus::Preparing,
            );
            $allReady = $order->items->every(
                fn (OrderItem $line) => $line->kitchen_status === KitchenStatus::Ready,
            );

            if ($allReady && $order->status !== OrderStatus::Ready) {
                if ($order->status === OrderStatus::Submitted) {
                    $this->state->transition($order, OrderStatus::Preparing);
                    $order->refresh();
                }

                if ($order->status === OrderStatus::Preparing) {
                    $this->state->transition($order, OrderStatus::Ready);
                    OrderReady::dispatch($order->fresh());
                }
            } elseif ($anyPreparing && $order->status === OrderStatus::Submitted) {
                $this->state->transition($order, OrderStatus::Preparing);
            }

            return $item->fresh(['order']);
        });
    }
}
