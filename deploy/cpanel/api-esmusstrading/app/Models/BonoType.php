<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BonoType extends Model
{
    protected $fillable = [
        'branch_id', 'name', 'code', 'menu_item_id', 'menu_category_id',
        'unit_value', 'redemption_department', 'active',
    ];

    protected function casts(): array
    {
        return [
            'unit_value' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @deprecated Use menuItems() — kept for legacy column reads. */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'bono_type_menu_item')
            ->withTimestamps();
    }

    public function balances(): HasMany
    {
        return $this->hasMany(BonoBalance::class);
    }
}
