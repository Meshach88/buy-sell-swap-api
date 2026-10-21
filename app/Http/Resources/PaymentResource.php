<?php

namespace App\Http\Resources;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'amount_kobo' => $this->amount_kobo,
            'amount_label' => Money::format($this->amount_kobo),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'order' => $this->whenLoaded('order', fn () => new OrderResource($this->order)),
        ];
    }
}
