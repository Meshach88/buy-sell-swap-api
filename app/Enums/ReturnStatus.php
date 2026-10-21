<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case Requested = 'requested';
    case SellerResponded = 'seller_responded';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Refunded = 'refunded';
}
