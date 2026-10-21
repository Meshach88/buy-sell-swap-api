<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case ToAssign = 'to_assign';
    case Assigned = 'assigned';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
