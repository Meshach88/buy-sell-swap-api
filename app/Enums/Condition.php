<?php

namespace App\Enums;

enum Condition: string
{
    case BrandNew = 'brand_new';
    case UkUsed = 'uk_used';
    case NgUsed = 'ng_used';
    case ForParts = 'for_parts';

    /** The words Nigerian buyers actually search with. */
    public function label(): string
    {
        return match ($this) {
            self::BrandNew => 'Brand new',
            self::UkUsed => 'UK used',
            self::NgUsed => 'Nigerian used',
            self::ForParts => 'For parts',
        };
    }
}
