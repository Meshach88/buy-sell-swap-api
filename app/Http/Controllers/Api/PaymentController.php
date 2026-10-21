<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentConfirmationService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private PaystackService $paystack,
        private PaymentConfirmationService $confirmation,
    ) {}

    /** Starts (or restarts, if the buyer abandoned/failed the first attempt) a Paystack transaction. */
    public function pay(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);
        abort_unless($order->status === OrderStatus::PendingPayment, 422, 'This order has already been paid for.');

        $user = $request->user();
        $email = $user->email ?: "{$user->phone}@buysellswap.ng"; // phone-only accounts have no email on file
        $reference = "{$order->number}-".now()->timestamp;

        $data = $this->paystack->initialize($order, $email, $reference);

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'paystack',
            'reference' => $reference,
            'amount_kobo' => $order->total_kobo,
            'status' => PaymentStatus::Pending->value,
            'expires_at' => now()->addMinutes(30),
        ]);

        return response()->json([
            'authorization_url' => $data['authorization_url'],
            'reference' => $reference,
            'public_key' => config('paystack.public_key'),
            'payment' => new PaymentResource($payment),
        ]);
    }

    /** Called by the web app right after Paystack redirects the buyer back — fast-path
     * confirmation. The webhook below is what guarantees this still happens even if
     * the buyer never makes it back here. */
    public function verify(Request $request, string $reference): PaymentResource
    {
        $payment = Payment::where('reference', $reference)->with('order')->firstOrFail();

        abort_unless($payment->order->buyer_id === $request->user()->id, 403);

        $payment = $this->confirmation->confirmByReference($reference) ?? $payment;

        return new PaymentResource($payment->load('order.sellerOrders', 'order.items'));
    }
}
