<?php

namespace App\Enums;

enum SettlementStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';
}
