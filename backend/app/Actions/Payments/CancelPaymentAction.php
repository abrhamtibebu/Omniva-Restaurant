<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\DomainException;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CancelPaymentAction
{
    public function __construct(private AuditLogger $audit) {}

    public function execute(Payment $payment, User $actor, string $action = 'cancelled'): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $action) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== PaymentStatus::Completed) {
                throw new DomainException('Only a completed payment can be reversed.');
            }

            $target = $action === 'refunded' ? PaymentStatus::Refunded : PaymentStatus::Cancelled;
            $from = $payment->status;

            $payment->status = $target;
            $payment->save();

            $this->audit->record(
                $target === PaymentStatus::Refunded ? 'payment.refunded' : 'payment.cancelled',
                $payment,
                ['status' => $from->value, 'amount' => (string) $payment->amount],
                ['status' => $target->value],
                $actor,
            );

            return $payment->fresh();
        });
    }
}
