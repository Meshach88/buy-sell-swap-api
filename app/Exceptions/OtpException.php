<?php

namespace App\Exceptions;

use Exception;

/** Thrown for every OTP failure so the controller can turn it into a 422 with a clear message. */
class OtpException extends Exception
{
    public static function noActiveCode(): self
    {
        return new self('No active code found. Request a new one.');
    }

    public static function expired(): self
    {
        return new self('This code has expired. Request a new one.');
    }

    public static function tooManyAttempts(): self
    {
        return new self('Too many attempts. Request a new code.');
    }

    public static function incorrect(): self
    {
        return new self('That code is incorrect.');
    }
}
