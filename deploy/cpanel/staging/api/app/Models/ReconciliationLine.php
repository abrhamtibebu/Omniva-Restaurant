<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationLine extends Model
{
    protected $fillable = [
        'reconciliation_id', 'line_kind', 'bono_type_id', 'inventory_item_id',
        'issued_quantity', 'sold_quantity', 'returned_quantity', 'unaccounted_quantity',
        'expected_quantity', 'physical_quantity', 'verified_quantity', 'expected_value',
    ];

    protected function casts(): array
    {
        return [
            'issued_quantity' => 'decimal:3',
            'sold_quantity' => 'decimal:3',
            'returned_quantity' => 'decimal:3',
            'unaccounted_quantity' => 'decimal:3',
            'expected_quantity' => 'decimal:3',
            'physical_quantity' => 'decimal:3',
            'verified_quantity' => 'decimal:3',
            'expected_value' => 'decimal:2',
        ];
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class);
    }

    public function bonoType(): BelongsTo
    {
        return $this->belongsTo(BonoType::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
