<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\PaymentConfirmationService;
use App\Services\PaystackService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public endpoint Paystack calls directly — no Sanctum auth, trust comes from
 * the signature instead. Every delivery is recorded in `payment_events` first
 * (unique on provider+event_id) so a retried webhook is a harmless no-op, and
 * only `charge.success` actually triggers PaymentConfirmationService — which
 * is itself idempotent, so even a duplicate `payment_events` row id-miss
 * (e.g. an id-less payload) can't double-apply a payment.
 */
class PaystackWebhookController extends Controller
{
    public function __construct(
        private PaystackService $paystack,
        private PaymentConfirmationService $confirmation,
    ) {}

    public function handle(Request $request): Response
    {
        $raw = $request->getContent();

        if (! $this->paystack->verifyWebhookSignature($raw, $request->header('x-paystack-signature'))) {
            abort(401);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? 'unknown';
        $reference = $payload['data']['reference'] ?? null;
        $eventId = $payload['data']['id'] ?? null;

        $payment = $reference ? Payment::where('reference', $reference)->first() : null;

        try {
            PaymentEvent::create([
                'payment_id' => $payment?->id,
                'provider' => 'paystack',
                'event' => $event,
                'event_id' => $eventId,
                'reference' => $reference,
                'payload' => $payload,
                'processed_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($eventId && str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->noContent(); // already recorded this exact delivery
            }

            throw $e;
        }

        if ($event === 'charge.success' && $reference) {
            $this->confirmation->confirmByReference($reference);
        }

        return response()->noContent();
    }
}
