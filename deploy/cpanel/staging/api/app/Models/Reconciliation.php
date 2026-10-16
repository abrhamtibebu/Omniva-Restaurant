<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reconciliation extends Model
{
    protected $fillable = [
        'branch_id', 'reference', 'department', 'department_session_id',
        'submitted_by', 'reviewer_id', 'status', 'expected_value',
        'submitted_cash', 'verified_cash', 'notes', 'submitted_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReconciliationStatus::class,
            'expected_value' => 'decimal:2',
            'submitted_cash' => 'decimal:2',
            'verified_cash' => 'decimal:2',
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReconciliationLine::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(PaymentEvidence::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(DepartmentSession::class, 'department_session_id');
    }
}
