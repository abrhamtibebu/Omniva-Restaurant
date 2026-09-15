<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Sequence;

class SequenceService
{
    /**
     * Allocate the next human-readable number, e.g. ORD-20260915-0001.
     *
     * The sequences row is locked for the duration of the surrounding
     * transaction so two concurrent creates cannot share a number. The unique
     * index on the consuming table is the last line of defence.
     */
    public function next(int $branchId, string $key, string $prefix): string
    {
        $period = now()->format('Ymd');

        $sequence = Sequence::query()->firstOrCreate(
            ['branch_id' => $branchId, 'key' => $key, 'period' => $period],
            ['next_number' => 1],
        );

        $sequence = Sequence::query()
            ->whereKey($sequence->id)
            ->lockForUpdate()
            ->firstOrFail();

        $number = $sequence->next_number;
        $sequence->increment('next_number');

        return sprintf('%s-%s-%04d', $prefix, $period, $number);
    }

    public function nextOrderNumber(int $branchId): string
    {
        return $this->next($branchId, 'order', 'ORD');
    }

    public function nextPurchaseNumber(int $branchId): string
    {
        return $this->next($branchId, 'purchase', 'PO');
    }
}
