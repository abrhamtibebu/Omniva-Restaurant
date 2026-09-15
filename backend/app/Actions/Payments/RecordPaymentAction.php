<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\PaymentCompleted;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class RecordPaymentAction
{
    public function __construct(private CompleteOrderAction $completeOrder) {}

    /**
     * @param  array{amount: string, payment_method: string, reference?: string|null, notes?: string|null}  $data
     */
    public function execute(Order $order, User $cashier, array $data): Payment
    {
        return DB::transaction(function () use ($order, $cashier, $data) {
            $order = Order::query()->with('payments')->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $order->status->canReceivePayments()) {
                throw new DomainException('Payments cannot be recorded against this order.');
            }

            $amount = Money::of((string) $data['amount']);

            if (! Money::isPositive($amount)) {
                throw new DomainException('Payment amount must be greater than zero.');
            }

            $balance = $order->balanceDue();

            if (Money::compare($amount, $balance) > 0) {
                throw new DomainException('Payment exceeds the remaining balance of '.$balance.'.');
            }

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'cashier_id' => $cashier->id,
                'amount' => $amount,
                'payment_method' => PaymentMethod::from($data['payment_method']),
                'status' => PaymentStatus::Completed,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => now(),
            ]);

            PaymentCompleted::dispatch($payment);

            $order->refresh()->load('payments');

            $paidInFull = Money::compare($order->balanceDue(), '0') <= 0;
            $readyToClose = $order->status === OrderStatus::Served
                || $order->type === OrderType::Takeaway;

            if ($paidInFull && $readyToClose) {
                $this->completeOrder->execute($order, $cashier);
            }

            return $payment->fresh('order');
        });
    }
}
