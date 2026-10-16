<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarRedemption extends Model
{
    protected $fillable = [
        'branch_id', 'barman_id', 'waiter_id', 'bono_type_id', 'inventory_item_id',
        'department_session_id', 'quantity', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function bonoType(): BelongsTo
    {
        return $this->belongsTo(BonoType::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }
}
