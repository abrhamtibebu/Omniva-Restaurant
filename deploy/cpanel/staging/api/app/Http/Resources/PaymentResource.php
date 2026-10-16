<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'cashier_id' => $this->cashier_id,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method->value,
            'status' => $this->status->value,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'cashier' => $this->whenLoaded('cashier', fn () => [
                'id' => $this->cashier->id,
                'name' => $this->cashier->name,
            ]),
        ];
    }
}
