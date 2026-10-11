<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockLocationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockLocation extends Model
{
    protected $fillable = ['branch_id', 'name', 'type', 'active'];

    protected function casts(): array
    {
        return [
            'type' => StockLocationType::class,
            'active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }
}
