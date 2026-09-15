<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OrderCalculator;
use Illuminate\Support\Facades\DB;

class RemoveOrderItemAction
{
    public function __construct(
        private OrderCalculator $calculator,
        private AuditLogger $audit,
    ) {}

    public function execute(Order $order, OrderItem $item, User $actor): void
    {
        if ($item->order_id !== $order->id) {
            throw new DomainException('Item does not belong to this order.', 404);
        }

        if (! $order->status->isOpen()) {
            throw new DomainException('This order can no longer be changed.');
        }

        DB::transaction(function () use ($order, $item, $actor) {
            $item = OrderItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($item->submitted_to_kitchen) {
                $this->audit->record(
                    'order_item.removed_after_submit',
                    $item,
                    [
                        'item_name_snapshot' => $item->item_name_snapshot,
                        'quantity' => $item->quantity,
                    ],
                    null,
                    $actor,
                );
            }

            $item->delete();
            $this->calculator->recalculate($order);
        });
    }
}
