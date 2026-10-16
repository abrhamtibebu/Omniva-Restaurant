<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockRequest extends Model
{
    protected $fillable = [
        'branch_id', 'reference', 'requester_id', 'source_location_id',
        'destination_location_id', 'department_session_id', 'status',
        'idempotency_key', 'notes', 'submitted_at', 'issued_at', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'submitted_at' => 'datetime',
            'issued_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockRequestLine::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'destination_location_id');
    }
}
