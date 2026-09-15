<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryUnit;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'sku',
        'unit',
        'quantity_on_hand',
        'minimum_stock',
        'average_cost',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'unit' => InventoryUnit::class,
            'quantity_on_hand' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'average_cost' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function isLowStock(): bool
    {
        return (float) $this->quantity_on_hand <= (float) $this->minimum_stock;
    }
}
