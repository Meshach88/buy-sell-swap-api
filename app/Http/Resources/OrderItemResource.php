<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'listing_id' => $this->listing_id,
            'title' => $this->title,
            'unit_price_kobo' => $this->unit_price_kobo,
            'unit_price_label' => Money::format($this->unit_price_kobo),
            'quantity' => $this->quantity,
            'total_kobo' => $this->total_kobo,
            'total_label' => Money::format($this->total_kobo),
            'image_url' => $this->whenLoaded('listing', fn () => $this->listing?->images->first()
                ? asset('storage/'.$this->listing->images->first()->path)
                : null
            ),
        ];
    }
}
