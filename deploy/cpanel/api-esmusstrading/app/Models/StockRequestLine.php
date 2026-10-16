<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRequestLine extends Model
{
    protected $fillable = [
        'stock_request_id', 'inventory_item_id', 'requested_quantity',
        'approved_quantity', 'issued_quantity', 'received_quantity',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:3',
            'approved_quantity' => 'decimal:3',
            'issued_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class, 'stock_request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
