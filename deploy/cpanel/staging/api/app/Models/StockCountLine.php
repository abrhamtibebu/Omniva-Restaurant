<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends Model
{
    protected $fillable = [
        'stock_count_id', 'inventory_item_id', 'expected_quantity',
        'physical_quantity', 'variance_quantity',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'physical_quantity' => 'decimal:3',
            'variance_quantity' => 'decimal:3',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
