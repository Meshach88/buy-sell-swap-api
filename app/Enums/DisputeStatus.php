<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case Open = 'open';
    case Reviewing = 'reviewing';
    case Resolved = 'resolved';
}
