<?php

namespace App\Exceptions;

use Exception;

/** Thrown when Paystack's API itself reports failure (bad key, network error,
 * declined-at-init, etc) — distinct from a webhook/verify telling us a charge failed. */
class PaystackException extends Exception
{
    public static function requestFailed(string $reason): self
    {
        return new self("Paystack request failed: {$reason}");
    }
}
