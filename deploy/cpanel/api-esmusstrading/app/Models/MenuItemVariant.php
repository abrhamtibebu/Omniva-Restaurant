<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MenuItemVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemVariant extends Model
{
    /** @use HasFactory<MenuItemVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'menu_item_id',
        'name',
        'price',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
