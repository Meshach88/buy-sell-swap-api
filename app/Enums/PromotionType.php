<?php

namespace App\Enums;

enum PromotionType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case FreeDelivery = 'free_delivery';
}
