<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentSalesSummary extends Model
{
    protected $fillable = [
        'branch_id', 'department_session_id', 'business_date', 'department',
        'menu_item_id', 'item_name_snapshot', 'quantity', 'unit_price_snapshot',
        'value_snapshot', 'source', 'entered_by',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'quantity' => 'decimal:3',
            'unit_price_snapshot' => 'decimal:2',
            'value_snapshot' => 'decimal:2',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
