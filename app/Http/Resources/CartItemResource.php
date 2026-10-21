<?php

namespace App\Http\Resources;

use App\Enums\ListingStatus;
use App\Models\CartItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CartItem */
class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $listing = $this->listing;
        $available = $listing->status === ListingStatus::Live && $listing->quantity > 0;

        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'line_total_kobo' => $listing->price_kobo * $this->quantity,
            'line_total_label' => Money::format($listing->price_kobo * $this->quantity),
            'available' => $available,
            'max_quantity' => $available ? $listing->quantity : 0,
            'listing' => new ListingResource($listing),
        ];
    }
}
