<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'inventory_item_id', 'from_location_id', 'to_location_id', 'type', 'quantity',
        'reference_type', 'reference_id', 'idempotency_key', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
