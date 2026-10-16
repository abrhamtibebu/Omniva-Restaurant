<?php

declare(strict_types=1);

namespace App\Enums;

enum TransferStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case PartiallyApproved = 'partially_approved';
    case Rejected = 'rejected';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Confirmed = 'confirmed';
    case DiscrepancyReview = 'discrepancy_review';

    public function isLocked(): bool
    {
        return in_array($this, [self::Confirmed, self::Rejected, self::DiscrepancyReview], true);
    }
}
