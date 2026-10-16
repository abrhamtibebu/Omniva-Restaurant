<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvidence extends Model
{
    protected $table = 'payment_evidence';

    protected $fillable = [
        'branch_id', 'uploaded_by', 'reconciliation_id',
        'file_path', 'file_hash',
        'method', 'amount', 'reference',
        'payee_name', 'account_number', 'phone', 'transaction_reference',
        'ocr_raw_text', 'ocr_status',
        'verification_status', 'verified_by', 'verified_at',
    ];

    protected $appends = ['file_url'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function getFileUrlAttribute(): ?string
    {
        if (! $this->id || ! $this->file_hash) {
            return null;
        }

        return "/bono/transfer-receipts/{$this->id}/file";
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
