<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OrderCalculator;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ApplyDiscountAction
{
    public function __construct(
        private OrderCalculator $calculator,
        private AuditLogger $audit,
    ) {}

    public function execute(Order $order, string $amount, User $actor): Order
    {
        if (! $order->status->isOpen()) {
            throw DomainException::trans('messages.order.discount_closed');
        }

        if (Money::compare($amount, '0') < 0) {
            throw DomainException::trans('messages.order.discount_negative');
        }

        return DB::transaction(function () use ($order, $amount, $actor) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $old = (string) $order->discount;

            $order->discount = Money::of($amount);
            $order->save();

            $this->calculator->recalculate($order);

            if (Money::compare($amount, (string) $order->subtotal) > 0) {
                throw DomainException::trans('messages.order.discount_exceeds_subtotal');
            }

            $this->audit->record(
                'order.discount_applied',
                $order,
                ['discount' => $old],
                ['discount' => (string) $order->discount],
                $actor,
            );

            return $order->fresh(['items.modifiers']);
        });
    }
}
