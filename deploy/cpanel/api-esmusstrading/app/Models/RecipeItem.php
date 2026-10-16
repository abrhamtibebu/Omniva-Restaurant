<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeItem extends Model
{
    protected $fillable = [
        'menu_item_id',
        'inventory_item_id',
        'quantity_required',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'quantity_required' => 'decimal:3',
            'unit' => InventoryUnit::class,
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
