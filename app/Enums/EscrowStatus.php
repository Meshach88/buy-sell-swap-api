<?php

namespace App\Enums;

enum EscrowStatus: string
{
    case Held = 'held';
    case Released = 'released';
    case Refunded = 'refunded';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Held => 'Held in escrow',
            self::Released => 'Released to seller',
            self::Refunded => 'Refunded to buyer',
            self::Disputed => 'Disputed',
        };
    }
}
