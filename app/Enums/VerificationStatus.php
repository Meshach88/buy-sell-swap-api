<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case NotRequired = 'not_required';
    case Requested = 'requested';
    case InReview = 'in_review';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case NeedsInfo = 'needs_info';
}
