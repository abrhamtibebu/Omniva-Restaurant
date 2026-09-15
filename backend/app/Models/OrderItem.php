<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KitchenStatus;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'menu_item_variant_id',
        'item_name_snapshot',
        'price_snapshot',
        'quantity',
        'subtotal',
        'notes',
        'kitchen_status',
        'submitted_to_kitchen',
    ];

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'kitchen_status' => KitchenStatus::class,
            'submitted_to_kitchen' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariant::class, 'menu_item_variant_id');
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class);
    }
}
