<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BonoHandover extends Model
{
    protected $fillable = [
        'branch_id', 'reference', 'kind', 'issuer_id', 'receiver_id',
        'department_session_id', 'status', 'idempotency_key', 'notes',
        'submitted_at', 'issued_at', 'confirmed_at',
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
        return $this->hasMany(BonoHandoverLine::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(DepartmentSession::class, 'department_session_id');
    }
}
