<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonoLedgerEntry extends Model
{
    protected $fillable = [
        'branch_id', 'bono_type_id', 'holder_type', 'holder_id', 'direction',
        'quantity', 'movement_type', 'reference_type', 'reference_id',
        'idempotency_key', 'notes', 'actor_id',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function bonoType(): BelongsTo
    {
        return $this->belongsTo(BonoType::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
