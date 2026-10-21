<?php

namespace App\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Seller = 'seller';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';
}
