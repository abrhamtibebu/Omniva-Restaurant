<?php

declare(strict_types=1);

namespace App\Enums;

enum ReconciliationStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Discrepancy = 'discrepancy';
    case Returned = 'returned';
    case Closed = 'closed';

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }
}
