<?php

namespace App\Services;

use App\Exceptions\PaystackException;
use App\Models\Order;
use Illuminate\Support\Facades\Http;

/** Thin wrapper around the two Paystack calls this app needs: starting a
 * transaction at checkout, and re-verifying one server-to-server — both the
 * redirect-back path and the webhook call verify() rather than trusting
 * whatever status either of them was handed, per Paystack's own guidance. */
class PaystackService
{
    public function initialize(Order $order, string $email, string $reference): array
    {
        $response = Http::withToken(config('paystack.secret_key'))
            ->post(config('paystack.base_url').'/transaction/initialize', [
                'email' => $email,
                'amount' => $order->total_kobo, // Paystack also bills NGN in kobo
                'reference' => $reference,
                'callback_url' => config('paystack.callback_url'),
                'metadata' => ['order_id' => $order->id, 'order_number' => $order->number],
            ]);

        if ($response->failed() || ! $response->json('status')) {
            throw PaystackException::requestFailed($response->json('message') ?? $response->reason());
        }

        return $response->json('data'); // ['authorization_url', 'access_code', 'reference']
    }

    public function verify(string $reference): array
    {
        $response = Http::withToken(config('paystack.secret_key'))
            ->get(config('paystack.base_url')."/transaction/verify/{$reference}");

        if ($response->failed() || ! $response->json('status')) {
            throw PaystackException::requestFailed($response->json('message') ?? $response->reason());
        }

        return $response->json('data'); // ['status' => 'success'|'failed'|..., 'amount', 'reference', ...]
    }

    /** Paystack signs the raw webhook body with HMAC SHA512 of the secret key. */
    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (! $signatureHeader) {
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, (string) config('paystack.secret_key'));

        return hash_equals($expected, $signatureHeader);
    }
}
