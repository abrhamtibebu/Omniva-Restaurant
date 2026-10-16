<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonoBalance extends Model
{
    protected $fillable = [
        'branch_id', 'bono_type_id', 'holder_type', 'holder_id', 'quantity',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function bonoType(): BelongsTo
    {
        return $this->belongsTo(BonoType::class);
    }
}
