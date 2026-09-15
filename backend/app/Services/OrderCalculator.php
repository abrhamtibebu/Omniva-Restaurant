<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Support\Money;

class OrderCalculator
{
    /**
     * Recalculate totals from line items. The frontend never supplies these.
     */
    public function recalculate(Order $order): Order
    {
        $order->loadMissing('items.modifiers');

        $subtotal = '0.00';

        foreach ($order->items as $item) {
            $unit = (string) $item->price_snapshot;

            foreach ($item->modifiers as $modifier) {
                $unit = Money::add($unit, (string) $modifier->price_snapshot);
            }

            $line = Money::multiply($unit, (string) $item->quantity);
            $item->subtotal = $line;
            $item->save();

            $subtotal = Money::add($subtotal, $line);
        }

        $discount = Money::of((string) $order->discount);
        $taxable = Money::max(Money::subtract($subtotal, $discount), '0');
        $tax = Money::percent($taxable, (string) $order->tax_rate);
        $total = Money::add($taxable, $tax);

        $order->forceFill([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
        ])->save();

        return $order->refresh();
    }
}
