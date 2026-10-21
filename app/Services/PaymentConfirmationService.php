<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The single place that turns a Paystack transaction into a paid Order.
 * Both the buyer's redirect-back verify call and the webhook call
 * confirmByReference() — whichever arrives first wins, the other is a no-op.
 * Never trusts the caller's claimed status: always re-checks with Paystack.
 */
class PaymentConfirmationService
{
    public function __construct(private PaystackService $paystack) {}

    public function confirmByReference(string $reference): ?Payment
    {
        $payment = Payment::where('reference', $reference)->first();

        if (! $payment) {
            Log::warning("Paystack confirm: no local payment for reference {$reference}");

            return null;
        }

        if ($payment->status === PaymentStatus::Success) {
            return $payment;
        }

        $data = $this->paystack->verify($reference);

        return match ($data['status'] ?? null) {
            'success' => $this->markSuccess($payment, $data),
            'failed', 'abandoned' => $this->markFailed($payment),
            default => $payment, // still pending on Paystack's side
        };
    }

    private function markSuccess(Payment $payment, array $data): Payment
    {
        if ((int) ($data['amount'] ?? 0) !== $payment->amount_kobo) {
            Log::error("Paystack amount mismatch for {$payment->reference}: expected {$payment->amount_kobo}, got ".($data['amount'] ?? 'null'));

            return $payment;
        }

        return DB::transaction(function () use ($payment, $data) {
            // Lock so a near-simultaneous webhook + redirect-verify can't both apply this.
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($locked->status === PaymentStatus::Success) {
                return $locked;
            }

            $locked->forceFill([
                'status' => PaymentStatus::Success->value,
                'method' => $this->mapMethod($data['channel'] ?? null),
                'provider_reference' => $data['id'] ?? null,
                'fee_kobo' => (int) ($data['fees'] ?? 0),
                'paid_at' => now(),
            ])->save();

            $order = $locked->order;
            $order->forceFill(['status' => OrderStatus::Paid->value, 'paid_at' => now()])->save();

            foreach ($order->items()->with('listing')->get() as $item) {
                // Clamp so a listing that sold out elsewhere in the meantime never goes negative.
                $item->listing()->decrement('quantity', min($item->quantity, $item->listing->quantity));
            }

            return $locked->refresh();
        });
    }

    private function markFailed(Payment $payment): Payment
    {
        $payment->forceFill(['status' => PaymentStatus::Failed->value])->save();

        return $payment;
    }

    /** Paystack's channel values don't line up 1:1 with our transfer|card|ussd enum. */
    private function mapMethod(?string $channel): ?string
    {
        return match ($channel) {
            'card' => 'card',
            'ussd' => 'ussd',
            'bank', 'bank_transfer', 'eft' => 'transfer',
            default => null,
        };
    }
}
