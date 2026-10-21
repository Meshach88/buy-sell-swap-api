<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Live = 'live';
    case OutOfStock = 'out_of_stock';
    case Rejected = 'rejected';
    case Removed = 'removed';
}
