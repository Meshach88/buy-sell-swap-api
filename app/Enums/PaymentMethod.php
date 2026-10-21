<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Transfer = 'transfer';
    case Card = 'card';
    case Ussd = 'ussd';
}
