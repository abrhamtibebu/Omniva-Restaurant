<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscrepancyCase extends Model
{
    protected $fillable = [
        'branch_id', 'type', 'reference_type', 'reference_id',
        'expected_quantity', 'actual_quantity', 'variance_quantity',
        'expected_value', 'actual_value', 'reason', 'resolution', 'status',
        'opened_by', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'actual_quantity' => 'decimal:3',
            'variance_quantity' => 'decimal:3',
            'expected_value' => 'decimal:2',
            'actual_value' => 'decimal:2',
            'resolved_at' => 'datetime',
        ];
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
