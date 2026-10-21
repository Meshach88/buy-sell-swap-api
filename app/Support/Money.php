<?php

namespace App\Support;

/**
 * All money in the database and API is whole kobo (₦1 = 100 kobo). Never floats.
 */
final class Money
{
    public static function fromNaira(int|float|string $naira): int
    {
        return (int) round(((float) $naira) * 100);
    }

    public static function toNaira(int $kobo): float
    {
        return $kobo / 100;
    }

    /** ₦415,000 (whole naira) or ₦415,000.50 when there are kobo. */
    public static function format(int $kobo): string
    {
        $decimals = $kobo % 100 === 0 ? 0 : 2;

        return '₦'.number_format($kobo / 100, $decimals);
    }

    /** Commission for a sale: rate in basis points (800 = 8%), rounded to the nearest kobo. */
    public static function commission(int $amountKobo, int $rateBps): int
    {
        return intdiv($amountKobo * $rateBps + 5_000, 10_000);
    }
}
