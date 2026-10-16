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

class UpdateOrderItemAction
{
    public function __construct(
        private OrderCalculator $calculator,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{quantity?: int, notes?: string|null}  $data
     */
    public function execute(Order $order, OrderItem $item, array $data, User $actor): OrderItem
    {
        if ($item->order_id !== $order->id) {
            throw DomainException::trans('messages.order.item_not_on_order', status: 404);
        }

        if (! $order->status->isOpen()) {
            throw DomainException::trans('messages.order.cannot_change');
        }

        return DB::transaction(function () use ($order, $item, $data, $actor) {
            $item = OrderItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $oldQuantity = $item->quantity;

            if (array_key_exists('quantity', $data)) {
                $newQuantity = (int) $data['quantity'];

                if ($newQuantity < 1) {
                    throw DomainException::trans('messages.order.quantity_at_least_one');
                }

                if ($item->submitted_to_kitchen && $newQuantity < $oldQuantity) {
                    $this->audit->record(
                        'order_item.quantity_reduced',
                        $item,
                        ['quantity' => $oldQuantity],
                        ['quantity' => $newQuantity],
                        $actor,
                    );
                }

                $item->quantity = $newQuantity;
            }

            if (array_key_exists('notes', $data)) {
                $item->notes = $data['notes'];
            }

            $item->save();
            $this->calculator->recalculate($order);

            return $item->fresh(['modifiers']);
        });
    }
}
