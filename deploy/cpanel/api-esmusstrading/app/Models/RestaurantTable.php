<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TableStatus;
use Database\Factories\RestaurantTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantTable extends Model
{
    /** @use HasFactory<RestaurantTableFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'capacity',
        'status',
        'active',
        'assigned_waiter_id',
        'current_order_id',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'status' => TableStatus::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedWaiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_waiter_id');
    }

    public function currentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'current_order_id');
    }
}
