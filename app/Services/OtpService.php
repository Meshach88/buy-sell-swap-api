<?php

namespace App\Services;

use App\Exceptions\OtpException;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generates and checks phone OTP codes. No SMS provider is connected yet
 * (see config/bss.php `otp.expose_debug_code`), so codes are logged and, in
 * non-production environments, handed back to the caller to display or send.
 */
class OtpService
{
    /** Generate a new code for the phone+purpose and invalidate any earlier unconsumed one. */
    public function generate(string $phone, string $purpose): string
    {
        OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = (string) random_int(0, (10 ** config('bss.otp.length')) - 1);
        $code = Str::padLeft($code, config('bss.otp.length'), '0');

        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(config('bss.otp.ttl_minutes')),
        ]);

        $this->send($phone, $code);

        return $code;
    }

    /** @throws OtpException */
    public function verify(string $phone, string $purpose, string $code): void
    {
        $otp = OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            throw OtpException::noActiveCode();
        }

        if ($otp->isExpired()) {
            throw OtpException::expired();
        }

        if ($otp->attempts >= config('bss.otp.max_attempts')) {
            throw OtpException::tooManyAttempts();
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            throw OtpException::incorrect();
        }

        $otp->forceFill(['consumed_at' => now()])->save();
    }

    private function send(string $phone, string $code): void
    {
        // TODO: replace with a real SMS provider once one is chosen (Termii,
        // Africa's Talking, etc. all have Laravel-friendly HTTP APIs).
        Log::info("OTP for {$phone}: {$code}");
    }
}
