<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending'; // registered, phone not verified yet
    case Active = 'active';
    case Suspended = 'suspended';
}
