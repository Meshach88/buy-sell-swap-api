<?php

namespace App\Enums;

enum SellerOrderStatus: string
{
    case New = 'new';
    case Accepted = 'accepted';
    case Packed = 'packed';
    case WithRider = 'with_rider';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New order',
            self::Accepted => 'Accepted',
            self::Packed => 'Packed',
            self::WithRider => 'With rider',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    /** The only forward step allowed from here (seller-driven fulfilment is strictly linear). */
    public function next(): ?self
    {
        return match ($this) {
            self::New => self::Accepted,
            self::Accepted => self::Packed,
            self::Packed => self::WithRider,
            self::WithRider => self::Delivered,
            self::Delivered, self::Cancelled => null,
        };
    }
}
