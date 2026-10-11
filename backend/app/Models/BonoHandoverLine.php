<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonoHandoverLine extends Model
{
    protected $fillable = [
        'bono_handover_id', 'bono_type_id', 'requested_quantity',
        'approved_quantity', 'issued_quantity', 'received_quantity',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:3',
            'approved_quantity' => 'decimal:3',
            'issued_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
        ];
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(BonoHandover::class, 'bono_handover_id');
    }

    public function bonoType(): BelongsTo
    {
        return $this->belongsTo(BonoType::class);
    }
}
