<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Support\Money;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'branch_id',
        'table_id',
        'waiter_id',
        'customer_id',
        'type',
        'status',
        'subtotal',
        'discount',
        'tax',
        'tax_rate',
        'total',
        'notes',
        'opened_at',
        'completed_at',
        'cancelled_at',
        'inventory_deducted_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'total' => 'decimal:2',
            'opened_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'inventory_deducted_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function amountPaid(): string
    {
        $sum = '0.00';

        foreach ($this->payments as $payment) {
            if ($payment->status === PaymentStatus::Completed) {
                $sum = Money::add($sum, (string) $payment->amount);
            }
        }

        return $sum;
    }

    public function balanceDue(): string
    {
        return Money::max(Money::subtract((string) $this->total, $this->amountPaid()), '0');
    }

    public function isFullyPaid(): bool
    {
        return Money::compare($this->balanceDue(), '0') <= 0 && Money::isPositive((string) $this->total);
    }
}
