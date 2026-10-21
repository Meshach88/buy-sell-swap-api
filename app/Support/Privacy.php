<?php

namespace App\Support;

final class Privacy
{
    /** "Musa Ibrahim" -> "Musa I." — used anywhere a buyer's full name shouldn't be exposed
     * (e.g. to a seller), but a reviewer/order needs some human-readable identifier. */
    public static function firstNameLastInitial(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $first = array_shift($parts);
        $lastInitial = $parts ? mb_strtoupper(mb_substr(array_pop($parts), 0, 1)).'.' : '';

        return trim("{$first} {$lastInitial}");
    }
}
