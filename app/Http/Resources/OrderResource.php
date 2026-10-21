<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestPayment = $this->payments->sortByDesc('id')->first();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'delivery_method' => $this->delivery_method,
            'delivery_name' => $this->delivery_name,
            'delivery_phone' => $this->delivery_phone,
            'delivery_street' => $this->delivery_street,
            'delivery_area' => $this->delivery_area,
            'subtotal_kobo' => $this->subtotal_kobo,
            'subtotal_label' => Money::format($this->subtotal_kobo),
            'delivery_fee_kobo' => $this->delivery_fee_kobo,
            'delivery_fee_label' => Money::format($this->delivery_fee_kobo),
            'discount_kobo' => $this->discount_kobo,
            'total_kobo' => $this->total_kobo,
            'total_label' => Money::format($this->total_kobo),
            'placed_at' => $this->placed_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => OrderItemResource::collection($this->items)),
            'seller_orders' => $this->whenLoaded('sellerOrders', fn () => SellerOrderResource::collection($this->sellerOrders)),
            'payment_status' => $latestPayment?->status->value,
            'payment_reference' => $latestPayment?->reference,
        ];
    }
}
